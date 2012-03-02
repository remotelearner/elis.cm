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

require_once CURMAN_DIRLOCATION.'/lib/managementpage.class.php';
require_once CURMAN_DIRLOCATION.'/form/cmform.class.php';
require_once CURMAN_DIRLOCATION.'/plugins/cluster_classification/clusterclassification.class.php';

class clusterclassificationform extends cmform {
    function definition() {
        global $CFG, $CURMAN;
        parent::definition();

        $strrequired = get_string('required');

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('hidden', PARAM_INT);

        $shortname = $mform->addElement('text', 'shortname', get_string('shortname', 'crlm_cluster_classification'));
        if(empty($this->_customdata['obj'])) {
            $mform->addRule('shortname', $strrequired, 'required', null, 'client');
        } else {
            $shortname->freeze();
        }
        $mform->addElement('text', 'name', get_string('name', 'crlm_cluster_classification'));
        $mform->addRule('name', $strrequired, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'param_autoenrol_curricula', get_string('autoenrol_curricula', 'crlm_cluster_classification'));
        $mform->addElement('advcheckbox', 'param_autoenrol_tracks', get_string('autoenrol_tracks', 'crlm_cluster_classification'));

        $mform->addElement('advcheckbox', 'param_autoenrol_groups', get_string('autoenrol_groups', 'crlm_cluster_classification'));
        $mform->setHelpButton('param_autoenrol_groups', array('autoenrol_groups', get_string('autoenrol_groups', 'crlm_cluster_classification'), 'crlm_cluster_classification'));

        $mform->addElement('advcheckbox', 'param_autoenrol_groupings', get_string('autoenrol_groupings', 'crlm_cluster_classification'));
        $mform->setHelpButton('param_autoenrol_groupings', array('autoenrol_groupings', get_string('autoenrol_groupings', 'crlm_cluster_classification'), 'crlm_cluster_classification'));

        // Add option for Alfresco shared organization space creation (if Alfresco code is present)
        if (file_exists($CFG->dirroot . '/file/repository/alfresco/repository.php') &&
            record_exists('block', 'name', 'repository')) {

            $mform->addElement('advcheckbox', 'param_alfresco_shared_folder',
                               get_string('alfresco_shared_folder', 'crlm_cluster_classification'));

            $button = array(
                'alfresco_shared_folder',
                get_string('alfresco_shared_folder', 'crlm_cluster_classification'),
                'crlm_cluster_classification'
            );

            $mform->setHelpButton('param_alfresco_shared_folder', $button);
        }

        $recs = $CURMAN->db->get_records(CLUSTERCLASSTABLE, '', '', 'name ASC', 'shortname, name');
        $options = array('' => get_string('same_classification', 'crlm_cluster_classification'));
        if ($recs) {
            foreach ($recs as $rec) {
                $options[$rec->shortname] = $rec->name;
            }
        }
        $mform->addElement('select', 'param_child_classification', get_string('child_classification', 'crlm_cluster_classification'), $options);

        $this->add_action_buttons();
    }
}

class clusterclassificationpage extends managementpage {
    var $data_class = 'clusterclassification';
    var $form_class = 'clusterclassificationform';
    var $pagename = 'clstclass';
    var $section = 'admn';

    var $view_columns = array('shortname', 'name');

    public function __construct($params=false) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => 'clusterclassificationpage', 'params' => array('action' => 'view'), 'name' => get_string('detail','block_curr_admin'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => 'clusterclassificationpage', 'params' => array('action' => 'edit'), 'name' => get_string('edit','block_curr_admin'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit.gif'),
        array('tab_id' => 'delete', 'page' => 'clusterclassificationpage', 'params' => array('action' => 'delete'), 'name' => get_string('delete_label','block_curr_admin') , 'showbutton' => true, 'image' => 'delete.gif'),
        );

        parent::__construct($params);
    }

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:config', $context);
    }

    public function get_title() {
        return get_string("manage{$this->data_class}s", 'crlm_cluster_classification');
    }

    public function get_navigation_default() {
        global $CFG;

        $page = $this->get_new_page();

        return array(array('name' => get_string("manage{$this->data_class}s", 'crlm_cluster_classification'),
                    'link'  => $page->get_url(),));
    }

    function action_default() {
        global $CURMAN;

        $sort       = optional_param('sort', 'name', PARAM_ALPHA);
        $dir        = optional_param('dir', 'ASC', PARAM_ALPHA);
        $namesearch = trim(optional_param('search', '', PARAM_TEXT));
        $alpha      = optional_param('alpha', '', PARAM_ALPHA);
        $page       = optional_param('page', 0, PARAM_INT);
        $perpage    = optional_param('perpage', 30, PARAM_INT);

        $clusterclass = new clusterclassification();

        $columns = array(
            'shortname' => get_string('shortname', 'crlm_cluster_classification'),
            'name' => get_string('name', 'crlm_cluster_classification'),
        );

        $records = $clusterclass->cluster_classification_listing($namesearch, $alpha, $page * $perpage, $perpage, $sort, $dir);
        $count = $CURMAN->db->count_records(CLUSTERCLASSTABLE); // total record count

        if (!empty($alpha) || !empty($namesearch)) {
            $count = $clusterclass->get_record_count(); // retrieve total count for search result
        }

        $this->print_list_view($records, $count, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }

    function print_add_button() {
        if (!$this->can_do('add')) {
            return;
        }

        $obj = $this->get_new_data_object();

        echo '<div align="center">';
        $options = array('s' => $this->pagename, 'action' => 'add');
        echo print_single_button('index.php', $options, get_string('add_cluster_classification','crlm_cluster_classification'), 'get', '_self', true, get_string('add','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'));
        echo '</div>';
    }

    function print_delete_button($obj) {
        echo '<div align="center">';
        $options = array('s' => $this->pagename, 'action' => 'delete', 'id' => $obj->id);
        echo print_single_button('index.php', $options, get_string('delete_cluster_classification','crlm_cluster_classification'), 'get', '_self', true, get_string('delete_label','block_curr_admin').' ' . get_string($obj->get_verbose_name(),'block_curr_admin'));
        echo '</div>';
    }
}
?>
