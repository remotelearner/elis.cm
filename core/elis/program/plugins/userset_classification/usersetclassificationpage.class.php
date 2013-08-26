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

require_once(dirname(__FILE__).'../../../../../config.php');
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once elispm::lib('managementpage.class.php');
require_once elispm::file('form/cmform.class.php');
require_once elispm::file('plugins/userset_classification/usersetclassification.class.php');

class usersetclassificationform extends cmform {
    function definition() {
        global $CFG, $DB;
        parent::definition();

        $strrequired = get_string('required');

        $mform = &$this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('hidden', PARAM_INT);

        $shortname = $mform->addElement('text', 'shortname', get_string('shortname', 'pmplugins_userset_classification'));
        if(empty($this->_customdata['obj'])) {
            $mform->addRule('shortname', $strrequired, 'required', null, 'client');
        } else {
            $shortname->freeze();
        }
        $mform->addElement('text', 'name', get_string('name', 'pmplugins_userset_classification'));
        $mform->addRule('name', $strrequired, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'param_autoenrol_curricula', get_string('autoenrol_curricula', 'pmplugins_userset_classification'));
        $mform->addElement('advcheckbox', 'param_autoenrol_tracks', get_string('autoenrol_tracks', 'pmplugins_userset_classification'));

        $mform->addElement('advcheckbox', 'param_autoenrol_groups', get_string('autoenrol_groups', 'pmplugins_userset_classification'));
        $mform->addHelpButton('param_autoenrol_groups', 'usersetclassificationform:autoenrol_groups', 'pmplugins_userset_classification');

        $mform->addElement('advcheckbox', 'param_autoenrol_groupings', get_string('autoenrol_groupings', 'pmplugins_userset_classification'));
        $mform->addHelpButton('param_autoenrol_groupings', 'usersetclassificationform:autoenrol_groupings', 'pmplugins_userset_classification');

        // Add option for Alfresco shared organization space creation (if Alfresco code is present)
        if (file_exists($CFG->dirroot . '/repository/elis_files/lib.php') &&
            $DB->record_exists('config_plugins', array('plugin'=> 'elis_files'))) {

            $mform->addElement('advcheckbox', 'param_elis_files_shared_folder',
                               get_string('elis_files_shared_folder', 'pmplugins_userset_classification'));
            $mform->addHelpButton('param_elis_files_shared_folder', 'usersetclassificationform:elis_files_shared_folder', 'pmplugins_userset_classification');
        }

        $recs = $DB->get_recordset(usersetclassification::TABLE, null, 'name ASC', 'shortname, name');
//                                                  ($table, array $conditions=null, $sort='', $fields='*', $limitfrom=0, $limitnum=0)
        $options = array('' => get_string('same_classification', 'pmplugins_userset_classification'));

        foreach ($recs as $rec) {
            $options[$rec->shortname] = $rec->name;
        }
        unset($recs);

        $mform->addElement('select', 'param_child_classification', get_string('child_classification', 'pmplugins_userset_classification'), $options);

        $this->add_action_buttons();
    }
}

class usersetclassificationpage extends managementpage {
    var $data_class = 'usersetclassification';
    var $form_class = 'usersetclassificationform';
    var $pagename = 'clstclass';
    var $section = 'admn';

    var $view_columns = array('shortname', 'name');

    public function __construct(array $params=null) {
        $this->tabs = array(
        array('tab_id' => 'view', 'page' => 'usersetclassificationpage', 'params' => array('action' => 'view'), 'name' => get_string('detail','elis_program'), 'showtab' => true),
        array('tab_id' => 'edit', 'page' => 'usersetclassificationpage', 'params' => array('action' => 'edit'), 'name' => get_string('edit','elis_program'), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
        array('tab_id' => 'delete', 'page' => 'usersetclassificationpage', 'params' => array('action' => 'delete'), 'name' => get_string('delete_label','elis_program') , 'showbutton' => true, 'image' => 'delete'),
        );

        parent::__construct($params);
    }

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('elis/program:config', $context);
    }

    public function get_title() {
        return get_string("manage{$this->data_class}s", 'pmplugins_userset_classification');
    }

    public function get_navigation_default() {
        global $CFG;

        $page = $this->get_new_page();

        return array(array('name' => get_string("manage{$this->data_class}s", 'pmplugins_userset_classification'),
                    'link'  => $page->get_url(),));
    }

    function display_default() {
        global $DB;

        $columns = array(
            'shortname' => array('header' => get_string('shortname','pmplugins_userset_classification')),
            'name'      => array('header' => get_string('name','pmplugins_userset_classification')),
        );

        $items = $DB->get_recordset(usersetclassification::TABLE, null, '', ('id, shortname, name'));
        $numitems = $DB->count_records(usersetclassification::TABLE);

        $this->print_list_view($items, $numitems, $columns, $filter=null, $alphaflag=true, $searchflag=true);
    }
}