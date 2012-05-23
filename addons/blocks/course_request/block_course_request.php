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
 * @subpackage blocks-course_request
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

//main lib file for the course request block
require_once($CFG->dirroot .'/blocks/course_request/lib.php');

class block_course_request extends block_base {
    function init() {
        $this->title   = get_string('blockname', 'block_course_request');
        $this->version = 2011062000;
    }

    function applicable_formats() {
        return array(
            'site' => true
        );
    }

    function has_config() {
        return true;
    }

    function get_content() {
        global $CFG;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $context = get_context_instance(CONTEXT_SYSTEM);

        $this->content = new object();
        $this->content->text = '';
        $this->content->footer = '';

        $items = array();

        //check request permissions
        if (block_course_request_can_do_request()) {
            $items[] = '<a href="'. $CFG->wwwroot .'/elis/program/index.php?action=default&s=crp">' .
                       get_string('courserequestpages', 'block_course_request') . '</a>';
        }

        if (has_capability('block/course_request:config', $context)) {
            //make sure custom fields are enabled for some context
            $allow_class_fields = !isset($CFG->block_course_request_use_class_fields) ||
                                  !empty($CFG->block_course_request_use_class_fields);
            $allow_course_fields = !empty($CFG->block_course_request_use_course_fields);

            if ($allow_class_fields || $allow_course_fields) {
                $items[] = '<a href="'. $CFG->wwwroot .'/elis/program/index.php?action=default&s=erp">' .
                           get_string('editrequestpages', 'block_course_request') . '</a>';
            }
        }

        if (has_capability('block/course_request:approve', $context)) {
            $items[] = '<a href="'. $CFG->wwwroot .'/elis/program/index.php?action=default&s=arp">' .
                       get_string('approvependingrequests', 'block_course_request') . '</a>';
        }

        if (!empty($items)) {
            $this->content->text .= implode('<br />', $items);
        }

        return $this->content;
    }
}

