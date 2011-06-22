<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2009 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2009 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once CURMAN_DIRLOCATION . '/lib/newpage.class.php';

/// The main management page.
class configcrsdefaultpage extends newpage {
    var $pagename = 'dftcrs';
    var $section = 'admn';

    function can_do_default() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:managecurricula', $context);
    }

    function get_navigation_default() {
        return array(
            array('name' => get_string('defaultcrs', 'block_curr_admin'),
                  'link' => $this->get_url()),
            );
    }

    function get_title_default() {
        return get_string('defaultcrs', 'block_curr_admin');
    }

    function _config_set_value($configdata, $key, $default = null) {
        if (isset($configdata->$key)) {
            $value = $configdata->$key;
        } else {
            $value = $default;
        }
        if ($value !== null) {
            cm_set_config($key, $value);
        }
    }

    function action_default() {
        global $CFG, $CURMAN;

        require_once($CFG->dirroot.'/curriculum/form/configcrsdefaultform.class.php');

        $configform = new configcrsdefaultform('index.php?s=dftcrs&section=admn', $this);
        $configform->set_data($CURMAN->config);

        if ($configdata = $configform->get_data()) {
            $this->_config_set_value($configdata, 'crsdftname', 0);
            $this->_config_set_value($configdata, 'crsdftcode', 0);
            $this->_config_set_value($configdata, 'crsdftidnumber');
            $this->_config_set_value($configdata, 'crsdftsyllabus', 0);
            $this->_config_set_value($configdata, 'crsdftlengthdescription', 0);
            $this->_config_set_value($configdata, 'crsdftlength', 0);
            $this->_config_set_value($configdata, 'crsdftcredits', 0);
            $this->_config_set_value($configdata, 'crsdftcompletion_grade', 0);
            $this->_config_set_value($configdata, 'crsdftenvironmentid', 0);
            $this->_config_set_value($configdata, 'crsdftcost', 0);
            $this->_config_set_value($configdata, 'crsdftversion', 0);
        }

        $configform->display();
    }
}

?>