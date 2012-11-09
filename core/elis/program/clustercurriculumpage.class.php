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

defined('MOODLE_INTERNAL') || die();

require_once elispm::lib('associationpage.class.php');
require_once elispm::lib('data/clustercurriculum.class.php');
require_once elispm::file('curriculumpage.class.php');
require_once elispm::file('usersetpage.class.php');
require_once elispm::file('form/clustercurriculumform.class.php');
require_once elispm::file('form/clustercurriculumeditform.class.php');

class clustercurriculumbasepage extends associationpage {

    var $data_class = 'clustercurriculum';
    var $form_class = 'clustercurriculumform';
    var $edit_form_class = 'clustercurriculumeditform';

    //var $tabs;

    function __construct(array $params=null) {

        $this->tabs = array(
            array('tab_id' => 'edit',
                  'page' => get_class($this),
                  'params' => array('action' => 'edit'),
                  'name' => get_string('edit', 'elis_program'),
                  'showtab' => true,
                  'showbutton' => true,
                  'image' => 'edit'),


            array('tab_id' => 'delete',
                  'page' => get_class($this),
                  'params' => array('action' => 'delete'),
                  'name' => get_string('delete_label', 'elis_program'),
                  'showbutton' => true,
                  'image' => 'delete'),
        );

        parent::__construct($params);
    }

    public static function get_contexts($capability) {
        if (!isset(clustercurriculumpage::$contexts[$capability])) {
            global $USER;
            clustercurriculumpage::$contexts[$capability] = get_contexts_by_capability_for_user('cluster', $capability, $USER->id);
        }
        return clustercurriculumpage::$contexts[$capability];
    }

    function can_do_add() {
        // the user must have 'elis/program:associate' permissions on both ends
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $curriculumid = $this->required_param('curriculumid', PARAM_INT);

        return usersetpage::_has_capability('elis/program:associate', $clusterid)
            && curriculumpage::_has_capability('elis/program:associate', $curriculumid);
    }

    /**
     * @todo Refactor this once we have a common save() method for datarecord subclasses.
     */
    function do_add() {
        $id = $this->required_param('id', PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $curriculumid = $this->required_param('curriculumid', PARAM_INT);

        //require_once elispm::file('/form/' . $this->form_class . '.class.php');
        //TODO: needs this plugin to be ported
//        require_once elispm::lib('/plugins/cluster_classification/clusterclassification.class.php');

        $target = $this->get_new_page(array('action'       => 'add',
                                            'id'           => $id,
                                            'clusterid'    => $clusterid,
                                            'curriculumid' => $curriculumid));


        $form = new $this->form_class($target->url, array('id'        => $id,
                                                          'clusterid' => $clusterid,
                                                          'curriculumid' => $curriculumid));

        $form->set_data(array('clusterid' => $clusterid,
                              'curriculumid' => $curriculumid));

        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else if($data = $form->get_data()) {
            clustercurriculum::associate($clusterid, $curriculumid, !empty($data->autoenrol));
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else {
            $this->display('add');
        }
    }

    /**
     * Prints the add form.
     * @param $parent_obj is the basic data object we are forming an association with.
     */
    function print_add_form($parent_obj) {
        $id = required_param('id', PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $curriculumid = $this->required_param('curriculumid', PARAM_INT);

        require_once elispm::file('plugins/userset_classification/usersetclassification.class.php');

        $target = $this->get_new_page(array('action'       => 'add',
                                            'id'           => $id));
        $form = new $this->form_class($target->url, array('id'        => $id));
        $form->set_data(array('id' => $id,
                              'clusterid' => $clusterid,
                              'curriculumid' => $curriculumid));
        $cluster_classification = usersetclassification::get_for_cluster($clusterid);
        if (!empty($cluster_classification->param_autoenrol_tracks)) {
            $form->set_data(array('autoenrol' => 1));
        } else {
            $form->set_data(array('autoenrol' => 0));
        }

        $form->display();
    }

    function can_do_edit() {
        // the user must have 'elis/program:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new clustercurriculum($association_id);
        $clusterid = $record->clusterid;
        $curriculumid = $record->curriculumid;

        return usersetpage::_has_capability('elis/program:associate', $clusterid)
            && curriculumpage::_has_capability('elis/program:associate', $curriculumid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    /**
     * handler for the edit action.  Prints the edit form.
     */
    function display_edit() { // do_edit()
        $association_id = required_param('association_id', PARAM_INT);
        $id             = required_param('id', PARAM_INT);
        $obj            = new $this->data_class($association_id);
        $parent_obj     = new $this->parent_data_class($id);

        /*if(!$obj->get_dbloaded()) { // TBD
            $sparam = new stdClass;
            $sparam->id = $id;
            print_error('invalid_objectid', 'elis_program', '', $sparam);
        }*/
        $this->print_edit_form($obj, $parent_obj);
    }

    function do_edit() {
        $id = $this->required_param('id', PARAM_INT);
        $association_id = $this->required_param('association_id', PARAM_INT);

        //require_once elispm::file('form/' . $this->edit_form_class . '.class.php');

        $target = $this->get_new_page(array('action'         => 'edit',
                                            'id'             => $id,
                                            'association_id' => $association_id));

        $form = new $this->edit_form_class($target->url, array('id' => $id,
                                                               'association_id' => $association_id));

        $form->set_data(array('id' => $id,
                              'association_id' => $association_id));

        if ($form->is_cancelled()) {
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else if($data = $form->get_data()) {
            clustercurriculum::update_autoenrol($association_id, $data->autoenrol);
            $target = $this->get_new_page(array('action' => 'default', 'id' => $id), true);
            redirect($target->url);
        } else {
            $this->display('edit');
        }
    }

    /**
     * Prints the edit form.
     * @param $obj The association object being edited.
     * @param $parent_obj The basic data object being associated with.
     */
    function print_edit_form($obj, $parent_obj) {
        $parent_id = required_param('id', PARAM_INT);
//        $association_id = required_param('association_id', PARAM_INT);

        $target = $this->get_new_page(array('action' => 'edit', 'id' => $parent_id));

        $form = new $this->edit_form_class($target->url);
        $form->set_data(array('id' => $parent_obj->id,
                              'association_id' => $obj->id));
        $form->display();
    }

    function create_table_object($items, $columns) {
        return new clustercurriculum_page_table($items, $columns, $this);
    }

}

class clustercurriculumpage extends clustercurriculumbasepage {
    var $pagename = 'clstcur';
    var $tab_page = 'usersetpage';
    //var $default_tab = 'clustercurriculumpage';

    var $parent_data_class = 'userset';
    var $section = 'users';

    const CPY_CURR_PREFIX         = 'add_curr_';
    const CPY_CURR_TRK_PREFIX     = 'add_trk_curr_';
    const CPY_CURR_CRS_PREFIX     = 'add_crs_curr_';
    const CPY_CURR_CLS_PREFIX     = 'add_cls_curr_';
    const CPY_CURR_MDLCRS_PREFIX  = 'add_mdlcrs_curr_';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (usersetpage::_has_capability('elis/program:userset_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return usersetpage::_has_capability('elis/program:associate', $id);
    }

    function display_default() {
        global $OUTPUT;

        $id           = $this->required_param('id', PARAM_INT);
        $sort         = $this->optional_param('sort', 'idnumber', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);

        $columns = array(
            'idnumber'    => array('header' => get_string('userset_idnumber', 'elis_program'),
                                   'decorator' => array(new record_link_decorator('curriculumpage',
                                                                                  array('action'=>'view'),
                                                                                  'curriculumid'),
                                                        'decorate')),
            'name'        => array('header' => get_string('userset_name', 'elis_program'),
                                   'decorator' => array(new record_link_decorator('curriculumpage',
                                                                                  array('action'=>'view'),
                                                                                  'curriculumid'),
                                                        'decorate')),
            'description' => array('header' => get_string('userset_description', 'elis_program')),
            'reqcredits'  => array('header' => get_string('program_reqcredits', 'elis_program')),
            'numcourses'  => array('header' => get_string('program_numcourses', 'elis_program')),
            'autoenrol'   => array('header' => get_string('usersetprogram_auto_enrol', 'elis_program')),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => array('header' => ''),
        );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'idnumber';
            $columns[$sort]['sortable'] = $dir;
        }

        //determine the full sort clause
        $sort_clause = $sort.' '.$dir;
        $items = clustercurriculum::get_curricula($id, 0, 0, $sort_clause);

        $this->print_list_view($items, $columns, 'curricula');

        // find the curricula that the user can associate with this cluster
        $contexts = curriculumpage::get_contexts('elis/program:associate');
        $curricula = curriculum_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        if (empty($curricula)) {
            $num_curricula = curriculum_count_records();
            if (!empty($num_curricula)) {
                // some curricula exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_curriculum', 'elis_program');
                echo '</div>';
            } else {
                // no curricula at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'elis_program');
                echo '</div>';
            }
        } else {
            echo '<p align="center"><center>';
            echo get_string('userset_addcurr_instruction','elis_program');
            echo $OUTPUT->help_icon('program_link','elis_program');
            $this->print_dropdown($curricula, $items, 'clusterid', 'curriculumid');
            echo '</center></p><br/>';
        }

        $options = array('id' => $id, 's' => 'clstcur', 'action' => 'copycurredit');
        $button = new single_button(new moodle_url('index.php', $options), get_string('userset_cpycurr','elis_program'), 'get');


        // Add a more specific CSS class
        $button->class = str_replace('singlebutton', 'singlebutton clscpycurrbtn ', $button->class);

        echo '<p align="center"><center>';
        echo get_string('userset_cpycurr_instruction','elis_program');
        echo $OUTPUT->help_icon('program_copy','elis_program');
        echo '</p><p align="center"><center>';
        echo $OUTPUT->render($button);
        echo '</center></p>';
    }

    function display_copycurredit() {
        global $CFG, $USER, $PAGE, $DB, $OUTPUT;

        $PAGE->requires->js('/elis/program/js/clustercurriculumpage.js');

        $id = $this->required_param('id', PARAM_INT);

        // Create a list of curricula to be excluded
        $curriculumshown = array();

        //$table = new stdClass();
        $table = new html_table();
        $table->head = array(get_string('userset_cpyclustname', 'elis_program'),
                             get_string('userset_cpycurname', 'elis_program'),
                             get_string('userset_cpyadd', 'elis_program'),
                             get_string('userset_cpytrkcpy', 'elis_program'),
                             get_string('userset_cpycrscpy', 'elis_program'),
                             get_string('userset_cpyclscpy', 'elis_program'),
                             get_string('userset_cpymdlclscpy', 'elis_program'),
            );

        $table->class = 'cluster_copy_curriculum';

        // Get all clusters
        $sort = 'name';
        $dir = 'ASC';
        $clusters = cluster_get_listing($sort, $dir, 0);
        $clusterlist = array();

        $sql = 'SELECT * from {' . userset::TABLE.'}';

        // Exclude clusters the user does not have the capability to manage/see
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

                    // Add to list of curricula to exclude
                    $curriculumshown[] = $assocurrrec->curriculumid;

                    // Skip over this clusters associated curricula
                    if ($clusid == $id) {
                        continue;
                    }

                    // Skip over curricula that user cannot associate
                    if (!$contexts->context_allowed($assocurrrec->curriculumid, 'curriculum')) {
                        continue;
                    }

                    if ($first) {
                        $curname = format_string($clusdata->name);
                        $first = false;
                    } else {
                        $curname = '';
                    }
                    // Disable the last 3 options unless the first is checked
                    $checkbox_ids = "'".self::CPY_CURR_PREFIX.$assocurrrec->curriculumid."',
                                     '".self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid."',
                                     '".self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid."',
                                     '".self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid."'";
                    $toggle_checkboxes = "cluster_copy_checkbox_changed(".$checkbox_ids.");";
                    $table->data[] = array($curname,
                                           format_string($assocurrrec->name),
                                           /*print_checkbox(self::CPY_CURR_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           print_checkbox(self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           print_checkbox(self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           print_checkbox(self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),*/
                                           html_writer::checkbox(self::CPY_CURR_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', array('id'=> self::CPY_CURR_PREFIX.$assocurrrec->curriculumid,
                                                                              'onclick' => $toggle_checkboxes)),
                                           html_writer::checkbox(self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', array('disabled' => true,
                                                                          'id' => self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid)),
                                           html_writer::checkbox(self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', array('disabled' => true,
                                                                          'id' => self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid)),
                                           html_writer::checkbox(self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', array('disabled' => true,
                                                                          'id' => self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid)),
                                           html_writer::select($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$assocurrrec->curriculumid),
                        );
                    $table->rowclass[] = 'clus_cpy_row';
                }
            }
        }

        // Add unassociated row to table
        $table->data[] = array(get_string('usersetprogram_unassociated', 'elis_program'),
                               '', '', '', '', '', '');
        $table->rowclass[] = 'clus_cpy_row unassigned';

        // Get all curriculums, removing curricula that have already
        // been listed
        $curriculums = curriculum_get_listing($sort, $dir, 0, 0, '', '', $contexts);
        foreach ($curriculums as $curriculumid => $curriculumdata) {
            if (false === array_search($curriculumid, $curriculumshown)) {
                $checkbox_ids = "'".self::CPY_CURR_PREFIX.$curriculumid."',
                                 '".self::CPY_CURR_TRK_PREFIX.$curriculumid."',
                                 '".self::CPY_CURR_CRS_PREFIX.$curriculumid."',
                                 '".self::CPY_CURR_CLS_PREFIX.$curriculumid."'";
                $toggle_checkboxes = "cluster_copy_checkbox_changed(".$checkbox_ids.");";
                $table->data[] = array('',
                                       format_string($curriculumdata->name),
                                       html_writer::checkbox(self::CPY_CURR_PREFIX.$curriculumid,
                                                      1, false, '', array('id'=> self::CPY_CURR_PREFIX.$curriculumid,
                                                                          'onclick' => $toggle_checkboxes)),
                                       html_writer::checkbox(self::CPY_CURR_TRK_PREFIX.$curriculumid,
                                                      1, false, '', array('disabled' => true,
                                                                          'id' => self::CPY_CURR_TRK_PREFIX.$curriculumid)),
                                       html_writer::checkbox(self::CPY_CURR_CRS_PREFIX.$curriculumid,
                                                      1, false, '', array('disabled' => true,
                                                                          'id' => self::CPY_CURR_CRS_PREFIX.$curriculumid)),
                                       html_writer::checkbox(self::CPY_CURR_CLS_PREFIX.$curriculumid,
                                                      1, false, '', array('disabled' => true,
                                                                          'id' => self::CPY_CURR_CLS_PREFIX.$curriculumid)),
                                       /*print_checkbox(self::CPY_CURR_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       print_checkbox(self::CPY_CURR_TRK_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       print_checkbox(self::CPY_CURR_CRS_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       print_checkbox(self::CPY_CURR_CLS_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),*/
//                                       choose_from_menu($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$curriculumid,
//                                                        '', '', '', 0, true),
                                       html_writer::select($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$curriculumid),
                    );

                $table->rowclass[] = 'clus_cpy_row';

            }
        }

        $currselectall = '<div class="currselectall">'.
                         '<a id="clus_currcpy_select_all" onclick="cluster_copycurriculum_set_all_selected()">'.
                         get_string('selectall').'</a></div>';

        $trkselectall = '<div class="trkselectall">'.
                         '<a id="clus_trkcpy_select_all" onclick="cluster_copytrack_set_all_selected()">'.
                         get_string('selectall').'</a></div>';

        $crsselectall = '<div class="crsselectall">'.
                         '<a id="clus_crscpy_select_all" onclick="cluster_copycourse_set_all_selected()">'.
                         get_string('selectall').'</a></div>';

        $clsselectall = '<div class="clsselectall">'.
                         '<a id="clus_crscpy_select_all" onclick="cluster_copyclass_set_all_selected()">'.
                         get_string('selectall').'</a></div>';

        $table->data[] = array('', '', $currselectall, $trkselectall, $crsselectall, $clsselectall);
        $table->rowclass[] = 'clus_cpy_row select_all_row';

        //echo print_table($table, true);
        echo html_writer::table($table);

        echo '<div class="clus_curr_cpy_save_exit">';
        echo '<input type="submit" name="save" value="'.get_string('userset_saveexit', 'elis_program').'">';
        echo '<div class="hidden">';
        echo '<input type="hidden" name="id" value="'.$id.'">';
        echo '<input type="hidden" name="s" value="clstcur">';
        echo '<input type="hidden" name="action" value="copycurr">';
        echo '<input type="hidden" name="sesskey" value="'. $USER->sesskey.'">';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }

    function do_copycurr() {
        global $CFG;

        // TODO: replace print_object messages with notice messages
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

        // Retrieve all of the curriculums that need to be copied and assigned
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

                /**
                 * The following block of code performs any necessary post-processing,
                 * primarily used for copying role assignments
                 */

                //we need to handle curricula first in case role assignments
                //at lower levels become redundant
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

class curriculumclusterpage extends clustercurriculumbasepage {
    var $pagename = 'curclst';
    var $tab_page = 'curriculumpage';
    var $parent_data_class = 'curriculum';
    //var $default_tab = 'curriculumclusterpage';

    var $section = 'curr';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (curriculumpage::_has_capability('elis/program:program_view', $id)) {
            //allow viewing but not managing associations
        	return true;
        }

        return curriculumpage::_has_capability('elis/program:associate', $id);
    }

    function display_default() {
        $id = $this->required_param('id', PARAM_INT);
        $sort = $this->optional_param('sort', 'name', PARAM_CLEAN);
        $dir = $this->optional_param('dir', 'ASC', PARAM_CLEAN);

        //this extra parameter signals a dependency on a parent cluster
        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $columns = array(
            'name'        => array('header' => get_string('program_name','elis_program'),
                                   'decorator' => array(new record_link_decorator('usersetpage',
                                                                                  array('action'=>'view'),
                                                                                  'clusterid'),
                                                        'decorate')),
            'display'     => array('header' => get_string('program_display','elis_program')),
            'autoenrol'   => array('header' => get_string('usersetprogram_auto_enrol', 'elis_program')),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => array('header' => ''),
        );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        $items = clustercurriculum::get_clusters($id, $parent_clusterid, $sort, $dir);

        $this->print_list_view($items, $columns, 'clusters');

        $contexts = usersetpage::get_contexts('elis/program:associate');
        $clusters = cluster_get_listing('name', 'ASC', 0, 0, '', '', array('contexts' =>$contexts));
        if (empty($clusters)) {
            $num_clusters = cluster_count_records();
            if (!empty($num_clusters)) {
                // some clusters exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_cluster', 'elis_program');
                echo '</div>';
            } else {
                // no clusters at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'elis_program');
                echo '</div>';
            }
        } else {
            $this->print_dropdown($clusters, $items, 'curriculumid', 'clusterid');
        }
    }

    /**
     * Creates a new table object with specified $items and $columns.
     * @param array $items
     * @param array $columns
     * @param array $formatters
     */
    function create_table_object($items, $columns) {

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $extra_params = array();
        if(!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }

        $page_object = $this->get_new_page($extra_params);

        return new clustercurriculum_page_table($items, $columns, $page_object);
    }
}

/**
 * This class is set up for displaying a cluster-curriculum association, and performs
 * special formatting on the yes/no autoenrol flag
 */
class clustercurriculum_page_table extends association_page_table {

    /**
     * Converts a 0 or 1 to a Yes or No display string for the autoenrol field
     *
     * @param   string  $column  The field name we are checking
     * @param   object  $item    The object containing the field in question
     *
     * @return  string           The converted text - Yes or No
     *
     */
    function get_item_display_autoenrol($column, $item) {
        return $this->display_yesno_item($column, $item);
    }

}

