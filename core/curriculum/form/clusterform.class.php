<?php
/*
*  ELIS(TM): Enterprise Learning Intelligence Suite
*
*  Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
*
*  This program is free software: you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation, either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  You should have received a copy of the GNU General Public License
*  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*  @package    elis
*  @subpackage curriculummanagement
*  @author     Remote-Learner.net Inc
*  @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
*  @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
*/

require_once(CURMAN_DIRLOCATION.'/form/cmform.class.php');
require_once(CURMAN_DIRLOCATION.'/clusterpage.class.php');

/**
 * form for adding clusters to cmclasses
 *
 * @copyright 29-Jun-2009 Olav Jordan <olav.jordan@remote-learner.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clusterform extends cmform {
    /**
     * items in the form
     */
    public function definition() {
        global $CURMAN, $CFG;

        parent::definition();

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');

        $mform->addElement('text', 'name', get_string('cluster_name', 'block_curr_admin') . ':');
        $mform->addRule('name', get_string('required'), 'required', NULL, 'client');
        $mform->setHelpButton('name', array('clusterform/name', get_string('cluster_name', 'block_curr_admin'), 'block_curr_admin'));

        $mform->addElement('textarea', 'display', get_string('cluster_description', 'block_curr_admin') . ':', array('cols'=>40, 'rows'=>2));
        $mform->setHelpButton('display', array('clusterform/display', get_string('cluster_description', 'block_curr_admin'), 'block_curr_admin'));

        $current_cluster_id = (isset($this->_customdata['obj']->id)) ? $this->_customdata['obj']->id : '';

        //obtain the non-child clusters that we could become the child of, with availability
        //determined based on the edit capability
        $contexts = clusterpage::get_contexts('block/curr_admin:cluster:edit');
        $non_child_clusters = cluster_get_non_child_clusters($current_cluster_id, $contexts);

        //parent dropdown
        $mform->addElement('select', 'parent', get_string('cluster_parent', 'block_curr_admin') . ':', $non_child_clusters);
        $mform->setHelpButton('parent', array('clusterform/parent', get_string('cluster_parent', 'block_curr_admin'), 'block_curr_admin'));

        // allow plugins to add their own fields
        $plugins = get_list_of_plugins('curriculum/cluster');

        $mform->addElement('header', 'userassociationfieldset', get_string('userassociation', 'block_curr_admin'));

        foreach ($plugins as $plugin) {
            require_once CURMAN_DIRLOCATION . '/cluster/' . $plugin . '/lib.php';
            call_user_func('cluster_' . $plugin . '_edit_form', $this);
        }

        // custom fields
        $fields = field::get_for_context_level('cluster');
        $fields = $fields ? $fields : array();

        $lastcat = null;
        $context = isset($this->_customdata['obj']) && isset($this->_customdata['obj']->id)
            ? get_context_instance(context_level_base::get_custom_context_level('cluster', 'block_curr_admin'), $this->_customdata['obj']->id)
            : get_context_instance(CONTEXT_SYSTEM);
        require_once CURMAN_DIRLOCATION.'/plugins/manual/custom_fields.php';
        foreach ($fields as $rec) {
            $field = new field($rec);
            if (!isset($field->owners['manual'])) {
                continue;
            }
            if ($lastcat != $rec->categoryid) {
                $lastcat = $rec->categoryid;
                $mform->addElement('header', "category_{$lastcat}", htmlspecialchars($rec->categoryname));
            }
            manual_field_add_form_element($this, $context, $field);
        }

        $this->add_action_buttons();
    }
}

/**
 * Confirm cluster deletion when the cluster has sub-clusters.  Prompt for
 * desired action (delete sub-clusters, promote sub-clusters).
 */
class clusterdeleteform extends cmform {
    public function definition() {
        global $CURMAN, $CFG;

        parent::definition();

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'confirm');
        $mform->setDefault('confirm', md5($this->_customdata['obj']->id));

        $radioarray = array();
        $radioarray[] = &$mform->createElement('radio', 'deletesubs', '', get_string('deletesubs', 'block_curr_admin'), 1);
        $radioarray[] = &$mform->createElement('radio', 'deletesubs', '', get_string('promotesubs', 'block_curr_admin'), 2);
        $mform->addGroup($radioarray, 'deletesubs', '', '<br />', false);
        $mform->setDefault('deletesubs',2);

        $this->add_action_buttons();
    }
}
?>
