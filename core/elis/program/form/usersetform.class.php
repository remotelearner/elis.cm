<?php
/*
*  ELIS(TM): Enterprise Learning Intelligence Suite
*
*  Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
*  @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
*/

defined('MOODLE_INTERNAL') || die();

require_once(elispm::file('form/cmform.class.php'));
require_once(elispm::file('usersetpage.class.php'));

/**
 * Form for adding and editing clusters
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usersetform extends cmform {
    /**
     * items in the form
     */
    public function definition() {
        global $CURMAN, $CFG;

        parent::definition();

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');

        $mform->addElement('text', 'name', get_string('userset_name', 'elis_program'));
        $mform->addRule('name', get_string('required'), 'required', NULL, 'client');
        $mform->addHelpButton('name', 'userset_name', 'elis_program');

        $mform->addElement('textarea', 'display', get_string('userset_description', 'elis_program'), array('cols'=>40, 'rows'=>2));
        $mform->addHelpButton('display', 'userset_description', 'elis_program');

        $current_cluster_id = (isset($this->_customdata['obj']->id)) ? $this->_customdata['obj']->id : '';

        //obtain the non-child clusters that we could become the child of, with availability
        //determined based on the edit capability
        $contexts = usersetpage::get_contexts('elis/program:userset_edit');
        $non_child_clusters = cluster_get_non_child_clusters($current_cluster_id, $contexts);

        //parent dropdown
        $mform->addElement('select', 'parent', get_string('userset_parent', 'elis_program'), $non_child_clusters);
        $mform->addHelpButton('parent', 'userset_parent', 'elis_program');

        // allow plugins to add their own fields

        $mform->addElement('header', 'userassociationfieldset', get_string('userset_userassociation', 'elis_program'));

        $plugins = get_plugin_list(userset::ENROL_PLUGIN_TYPE);
        foreach ($plugins as $plugin => $plugindir) {
            require_once(elis::plugin_file(userset::ENROL_PLUGIN_TYPE.'_'.$plugin, 'lib.php'));
            call_user_func('cluster_' . $plugin . '_edit_form', $this, $mform, $current_cluster_id);
        }

        // custom fields
        $this->add_custom_fields('cluster', 'elis/program:userset_edit', 'elis/program:userset_view', 'cluster');

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $errors += parent::validate_custom_fields($data, 'cluster');
        return $errors;
    }
}

/**
 * Confirm cluster deletion when the cluster has sub-clusters.  Prompt for
 * desired action (delete sub-clusters, promote sub-clusters).
 */
class usersetdeleteform extends cmform {
    public function definition() {
        global $CURMAN, $CFG;

        parent::definition();

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'confirm');
        $mform->setDefault('confirm', 1);
        $mform->setType('confirm', PARAM_INT);

        $radioarray = array();
        $radioarray[] = &$mform->createElement('radio', 'deletesubs', '', get_string('deletesubs', 'elis_program'), 1);
        $radioarray[] = &$mform->createElement('radio', 'deletesubs', '', get_string('promotesubs', 'elis_program'), 0);
        $mform->addGroup($radioarray, 'deletesubs', '', '<br />', false);
        $mform->setDefault('deletesubs', 0);

        $this->add_action_buttons();
    }
}
?>
