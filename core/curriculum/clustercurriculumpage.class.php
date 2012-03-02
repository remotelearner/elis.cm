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

require_once (CURMAN_DIRLOCATION . '/lib/clustercurriculum.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/associationpage.class.php');

require_once (CURMAN_DIRLOCATION . '/clusterpage.class.php');
require_once (CURMAN_DIRLOCATION . '/curriculumpage.class.php');

class clustercurriculumbasepage extends associationpage {

    var $data_class = 'clustercurriculum';
    var $form_class = 'clustercurriculumform';
    var $edit_form_class = 'clustercurriculumeditform';

    function __construct($params=false) {
        $this->tabs = array(
            array('tab_id' => 'edit',
                  'page' => get_class($this),
                  'params' => array('action' => 'edit'),
                  'name' => get_string('edit', 'block_curr_admin'),
                  'showtab' => true,
                  'showbutton' => true,
                  'image' => 'edit.gif'),
            array('tab_id' => 'delete',
                  'page' => get_class($this),
                  'params' => array('action' => 'delete'),
                  'name' => get_string('delete_label', 'block_curr_admin'),
                  'showbutton' => true,
                  'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_savenew() {
        // the user must have 'block/curr_admin:associate' permissions on both ends
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $curriculumid = $this->required_param('curriculumid', PARAM_INT);

        return clusterpage::_has_capability('block/curr_admin:associate', $clusterid)
            && curriculumpage::_has_capability('block/curr_admin:associate', $curriculumid);
    }

    /**
     * @todo Refactor this once we have a common save() method for datarecord subclasses.
     */
    function action_savenew() {
        $id = $this->required_param('id', PARAM_INT);
        $clusterid = $this->required_param('clusterid', PARAM_INT);
        $curriculumid = $this->required_param('curriculumid', PARAM_INT);

        require_once(CURMAN_DIRLOCATION . '/form/' . $this->form_class . '.class.php');
        require_once(CURMAN_DIRLOCATION . '/plugins/cluster_classification/clusterclassification.class.php');

        $target = $this->get_new_page(array('action'       => 'savenew',
                                            'id'           => $id,
                                            'clusterid'    => $clusterid,
                                            'curriculumid' => $curriculumid));

        $form = new $this->form_class($target->get_moodle_url(), array('id'        => $id,
                                                                       'clusterid' => $clusterid,
                                                                       'curriculumid' => $curriculumid));

        if($data = $form->get_data()) {
            if(!isset($data->cancel)) {
                clustercurriculum::associate($clusterid, $curriculumid, !empty($data->autoenrol));
            }
            $this->action_default();
        } else {
            $cluster_classification = clusterclassification::get_for_cluster($clusterid);
            if ($cluster_classification->param_autoenrol_curricula) {
                $form->set_data(array('autoenrol' => 1));
            } else {
                $form->set_data(array('autoenrol' => 0));
            }
            $form->display();
        }
    }

    function can_do_edit() {
        // the user must have 'block/curr_admin:associate' permissions on both
        // ends
        $association_id = $this->required_param('association_id', PARAM_INT);
        $record = new clustercurriculum($association_id);
        $clusterid = $record->clusterid;
        $curriculumid = $record->curriculumid;

        return clusterpage::_has_capability('block/curr_admin:associate', $clusterid)
            && curriculumpage::_has_capability('block/curr_admin:associate', $curriculumid);
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function action_edit() {
        $id = $this->required_param('id', PARAM_INT);
        $association_id = $this->required_param('association_id', PARAM_INT);

        require_once(CURMAN_DIRLOCATION . '/form/' . $this->edit_form_class . '.class.php');

        $target = $this->get_new_page(array('action'         => 'edit',
                                            'id'             => $id,
                                            'association_id' => $association_id));

        $form = new $this->edit_form_class($target->get_moodle_url(), array('id' => $id,
                                                                            'association_id' => $association_id));

        $form->set_data(array('id' => $id,
                              'association_id' => $association_id));

        if($data = $form->get_data()) {
            if(!isset($data->cancel)) {
                clustercurriculum::update_autoenrol($association_id, $data->autoenrol);
            }
            $this->action_default();
        } else {
            $form->display();
        }
    }

    function create_table_object($items, $columns, $formatters) {
        return new clustercurriculum_page_table($items, $columns, $this, $formatters);
    }

}

class clustercurriculumpage extends clustercurriculumbasepage {
    var $pagename = 'clstcur';
    var $tab_page = 'clusterpage';

    var $section = 'users';

    const CPY_CURR_PREFIX         = 'add_curr_';
    const CPY_CURR_TRK_PREFIX     = 'add_trk_curr_';
    const CPY_CURR_CRS_PREFIX     = 'add_crs_curr_';
    const CPY_CURR_CLS_PREFIX     = 'add_cls_curr_';
    const CPY_CURR_MDLCRS_PREFIX  = 'add_mdlcrs_curr_';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (clusterpage::_has_capability('block/curr_admin:cluster:view', $id)) {
            //allow viewing but not managing associations
            return true;
        }

        return clusterpage::_has_capability('block/curr_admin:associate', $id);
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);

        $sort = $this->optional_param('sort', 'name', PARAM_CLEAN);
        $dir = $this->optional_param('dir', 'ASC', PARAM_CLEAN);

        $columns = array(
            'idnumber'    => 'ID Number',
            'name'        => 'Name',
            'description' => 'Description',
            'reqcredits'  => 'Required Credits',
            'numcourses'  => 'Num Courses',
            'autoenrol'   => get_string('auto_enrol', 'block_curr_admin'),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => '',
        );

        $items = clustercurriculum::get_curricula($id, 0, 0, $sort, $dir);

        $formatters = $this->create_link_formatters(array('idnumber', 'name'), 'curriculumpage', 'curriculumid');

        $this->print_list_view($items, $columns, $formatters, 'curricula');

        // find the curricula that the user can associate with this cluster
        $contexts = curriculumpage::get_contexts('block/curr_admin:associate');
        $curricula = curriculum_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        if (empty($curricula)) {
            $num_curricula = curriculum_count_records();
            if (!empty($num_curricula)) {
                // some curricula exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_curriculum', 'block_curr_admin');
                echo '</div>';
            } else {
                // no curricula at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'block_curr_admin');
                echo '</div>';
            }
            return; // TBD: DO NOT display copy curriculum button!
        } else {
            echo '<p align="center"><center>';
            echo get_string('clsaddcurr_instruction','block_curr_admin');
            $this->print_dropdown($curricula, $items, 'clusterid', 'curriculumid');
            echo '</center></p><br/>';
        }

        $options = array('id' => $id, 's' => 'clstcur', 'action' => 'copycurredit');
        $button = print_single_button('index.php', $options, get_string('clscpycurr','block_curr_admin'), 'get', '_self', true);

        // Add a more specific CSS class
        $button = str_replace('singlebutton', 'singlebutton clscpycurrbtn ', $button);

        echo '<p align="center"><center>';
        echo get_string('clscpycurr_instruction','block_curr_admin');
        echo '</p><p align="center"><center>';
        echo $button;
        echo '</center></p>';
    }

    function action_copycurredit() {
        global $CFG, $USER, $CURMAN;

        require_js($CFG->wwwroot . '/curriculum/js/clustercurriculumpage.js');

        $id = $this->required_param('id', PARAM_INT);

        // Create a list of curricula to be excluded
        $curriculumshown = array();

        $table = new stdClass();
        $table->head = array(get_string('clustcpyclustname', 'block_curr_admin'),
                             get_string('clustcpycurname', 'block_curr_admin'),
                             get_string('clustcpyadd', 'block_curr_admin'),
                             get_string('clustcpytrkcpy', 'block_curr_admin'),
                             get_string('clustcpycrscpy', 'block_curr_admin'),
                             get_string('clustcpyclscpy', 'block_curr_admin'),
                             get_string('clustcpymdlclscpy', 'block_curr_admin'),
            );

        $table->class = 'cluster_copy_curriculum';

        // Get all clusters
        $sort = 'name';
        $dir = 'ASC';
        $clusters = cluster_get_listing($sort, $dir, 0);
        $clusterlist = array();

        $sql = 'SELECT * from ' . $CURMAN->db->prefix_table('crlm_cluster');

        // Exclude clusters the user does not have the capability to manage/see
        $context = get_contexts_by_capability_for_user('cluster', 'block/curr_admin:cluster:view', $USER->id);

        foreach ($clusters as $clusid => $clusdata) {
            $haspermission = $context->context_allowed($clusid, 'cluster');

            if (!$haspermission) {
                  unset($clusters[$clusid]);
            }
        }



        echo '<form action="index.php" method="post">';

        $mdlcrsoptions = array('copyalways' => get_string('currcopy_mdlcrs_copyalways', 'block_curr_admin'),
                               'copyautocreated' => get_string('currcopy_mdlcrs_copyautocreated', 'block_curr_admin'),
                               'autocreatenew' => get_string('currcopy_mdlcrs_autocreatenew', 'block_curr_admin'),
                               'link' => get_string('currcopy_mdlcrs_link', 'block_curr_admin')
            );

        $contexts = curriculumpage::get_contexts('block/curr_admin:associate');

        foreach ($clusters as $clusid => $clusdata) {

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
                    $table->data[] = array($curname,
                                           format_string($assocurrrec->name),
                                           print_checkbox(self::CPY_CURR_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           print_checkbox(self::CPY_CURR_TRK_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           print_checkbox(self::CPY_CURR_CRS_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           print_checkbox(self::CPY_CURR_CLS_PREFIX.$assocurrrec->curriculumid,
                                                          1, false, '', '', '', true),
                                           choose_from_menu($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$assocurrrec->curriculumid,
                                                            '', '', '', 0, true),
                        );
                    $table->rowclass[] = 'clus_cpy_row';
                }


            }
        }

        // Add unassociated row to table
        $table->data[] = array(get_string('unassociated', 'block_curr_admin'),
                               '', '', '', '', '', '');
        $table->rowclass[] = 'clus_cpy_row unassigned';

        // Get all curriculums, removing curricula that have already
        // been listed
        $curriculums = curriculum_get_listing($sort, $dir, 0, 0, '', '', $contexts);

        foreach ($curriculums as $curriculumid => $curriculumdata) {

            if (false === array_search($curriculumid, $curriculumshown)) {
                $table->data[] = array('',
                                       format_string($curriculumdata->name),
                                       print_checkbox(self::CPY_CURR_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       print_checkbox(self::CPY_CURR_TRK_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       print_checkbox(self::CPY_CURR_CRS_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       print_checkbox(self::CPY_CURR_CLS_PREFIX.$curriculumid,
                                                      1, false, '', '', '', true),
                                       choose_from_menu($mdlcrsoptions, self::CPY_CURR_MDLCRS_PREFIX.$curriculumid,
                                                        '', '', '', 0, true),
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

        echo print_table($table, true);

        echo '<div class="clus_curr_cpy_save_exit">';
        echo '<input type="submit" name="save" value="'.get_string('saveexit', 'block_curr_admin').'">';
        echo '<div class="hidden">';
        echo '<input type="hidden" name="id" value="'.$id.'">';
        echo '<input type="hidden" name="s" value="clstcur">';
        echo '<input type="hidden" name="action" value="copycurr">';
        echo '<input type="hidden" name="sesskey" value="'. $USER->sesskey.'">';
        echo '</div>';
        echo '</div>';
        echo '</form>';

    }

    function action_copycurr() {

        global $CFG;

        // TODO: replace print_object messages with notice messages
        $sesskey = required_param('sesskey', PARAM_TEXT);
        if (!confirm_sesskey($sesskey)) {
            print_error('invalidsesskey', 'error', 'index.php');
        }

        $data = (array) data_submitted();
        $clusterid = $this->required_param('id', PARAM_INT);

        if (empty($data)) {
            notify(get_string('nodatasubmit', 'block_curr_admin'), 'red');
        }

        $targetcluster = new cluster($clusterid);

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
                if ($this->optional_param(self::CPY_CURR_TRK_PREFIX.$currid, 0, PARAM_INT)) {
                    $options['tracks'] = true;
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
                        cmclasspage::after_cm_entity_add($class);
                    }
                }

                if (!empty($rv['curricula'])) {
                    $newcurr = new curriculum($rv['curricula'][$curr->id]);
                    $curr->newname = $newcurr->name;
                    notify(get_string('clustcpycurr', 'block_curr_admin', $curr), 'notifysuccess');
                }
            }
        }

        redirect($CFG->wwwroot . '/curriculum/index.php?id='.$data['id'].'&amp;s=clstcur', '', 2);

    }

}

class curriculumclusterpage extends clustercurriculumbasepage {
    var $pagename = 'curclst';
    var $tab_page = 'curriculumpage';

    var $section = 'curr';

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);

        if (curriculumpage::_has_capability('block/curr_admin:curriculum:view', $id)) {
            //allow viewing but not managing associations
            return true;
        }

        return curriculumpage::_has_capability('block/curr_admin:associate', $id);
    }

    function action_default() {
        $id = $this->required_param('id', PARAM_INT);
        $sort = $this->optional_param('sort', 'name', PARAM_CLEAN);
        $dir = $this->optional_param('dir', 'ASC', PARAM_CLEAN);

        //this extra parameter signals a dependency on a parent cluster
        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $columns = array(
            'name'        => 'Name',
            'display'     => 'Display',
            'autoenrol'   => get_string('auto_enrol', 'block_curr_admin'),
             //buttons triggers the use of "tabs" as buttons for editing and deleting
            'buttons'     => '',
        );

        $items = clustercurriculum::get_clusters($id, $parent_clusterid, $sort, $dir);

        $formatters = $this->create_link_formatters(array('name'), 'clusterpage', 'clusterid');

        $this->print_list_view($items, $columns, $formatters, 'clusters');

        $contexts = clusterpage::get_contexts('block/curr_admin:associate');
        $clusters = cluster_get_listing('name', 'ASC', 0, 0, '', '', array('contexts' =>$contexts));
        if (empty($clusters)) {
            $num_clusters = cluster_count_records();
            if (!empty($num_clusters)) {
                // some clusters exist, but don't have associate capability on
                // any of them
                echo '<div align="center"><br />';
                print_string('no_associate_caps_cluster', 'block_curr_admin');
                echo '</div>';
            } else {
                // no clusters at all
                echo '<div align="center"><br />';
                print_string('all_items_assigned', 'block_curr_admin');
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
    function create_table_object($items, $columns, $formatters) {

        $parent_clusterid = $this->optional_param('parent_clusterid', 0, PARAM_INT);

        $extra_params = array();
        if(!empty($parent_clusterid)) {
            $extra_params['parent_clusterid'] = $parent_clusterid;
        }

        $page_object = $this->get_new_page($extra_params);

        return new clustercurriculum_page_table($items, $columns, $page_object, $formatters);
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
        return $this->get_yesno_item_display($column, $item);
    }

}

?>
