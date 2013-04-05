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

require_once elis::lib('page.class.php');

abstract class pm_page extends elis_page {
    /**
     * The page's short name
     */
    var $pagename;

    protected function _get_page_url() {
        global $CFG;
        return "{$CFG->wwwroot}/elis/program/index.php";
    }

    protected function _get_page_type() {
        return 'elispm';
    }

    protected function _get_page_params() {
        return array('s' => $this->pagename) + parent::_get_page_params();
    }

    function build_navbar_default($who = null) {
        global $CFG;
        if (!$who) {
            $who = $this;
        }
        parent::build_navbar_default();
        $who->navbar->add( /* is_siteadmin() */ (true)
                           ? get_string('programmanagement', 'elis_program')
                           : get_string('learningplan', 'elis_program'),
                          "{$CFG->wwwroot}/elis/program/");
    }

    /**
     * Determines the name of the context class that represents this page's cm entity
     *
     * @return  string  The name of the context class that represents this page's cm entity
     *
     * @todo            Do something less complex to determine the appropriate class
     *                  (requires page class redesign)
     */
    function get_page_context() {
        $context = '';

        if (isset($this->parent_data_class)) {
            //parent data class is specified directly in the record
            $context = $this->parent_data_class;
        } else if (isset($this->parent_page) && isset($this->parent_page->data_class)) {
            //parent data class is specified indirectly through a parent page object
            $context = $this->parent_page->data_class;
        } else if (isset($this->tab_page)) {
            //a parent tab class exists
            $tab_page_class = $this->tab_page;

            //construct an instance of the named class and obtain its core data class
            $tab_page_class_instance = new $tab_page_class();
            $context = $tab_page_class_instance->data_class;
        } else if(isset($this->data_class)) {
            //out of other options, so directly use the data class associated with this page
            $context = $this->data_class;
        }

        return $context;
    }
}
