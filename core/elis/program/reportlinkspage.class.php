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

require_once elispm::lib('page.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::file('pmclasspage.class.php');

/// Page for linking to external class reports
class class_reportlinkspage extends pm_page {

    var $pagename = 'replnk';
    var $tab_page = 'pmclasspage';
    var $section = 'curr';

    function build_navbar_default($who = null) {
        global $CFG;
        parent::build_navbar_default($who);
        $this->navbar->add($this->get_page_title_default());
    }

    function get_page_title_default() {
        return get_string('classreportlinks', 'elis_program');
    }

    /**
     * Returns an instance of the page class that should provide the tabs for this association page.
     * This allows the association interface to be located "under" the general management interface for
     * the data object whose associations are being viewed or modified.
     *
     * @param $params
     * @return object
     */
    function get_tab_page($params=array()) {
        return new $this->tab_page($params);
    }

    function print_header($_) {
        $id = required_param('id', PARAM_INT);

        parent::print_header($_);

        $this->get_tab_page()->print_tabs(get_class($this), array('id' => $id));
    }

    function can_do_default() {
        global $USER;
        $id = $this->required_param('id', PARAM_INT);

        // TODO: Ugly, this needs to be overhauled
        $cpage = new pmclasspage();
        return $cpage->_has_capability('block/php_report:view', $id)
            || instructor::user_is_instructor_of_class(cm_get_crlmuserid($USER->id), $id);
    }

    function display_default() {
        global $CFG, $DB;

        echo '<ul>';

        $id = required_param('id', PARAM_INT); // the class id
        $record = $DB->get_record(pmclass::TABLE, array('id'=>$id));
        $course_id = isset($record->courseid) ? $record->courseid : 0; // the associated crlm course id (needed for course/class dependent filter on report)

        // Class roster report
        $class_roster_report_link = $CFG->wwwroot . '/blocks/php_report/render_report_page.php?report=class_roster&classid=' . $id . '&classid_parent=' . $course_id;
        $class_roster_report_name = get_string('classrosterreport', 'elis_program');
        echo '<li><a href="' . $class_roster_report_link . '">' . $class_roster_report_name . '</a></li>';

        // Class completion report
        $class_completion_report_link = $CFG->wwwroot . '/blocks/php_report/render_report_page.php?report=class_completion_gas_gauge&class=' . $id . '&class_parent=' . $course_id;
        $class_completion_report_name = get_string('classcompletionreport', 'elis_program');
        echo '<li><a href="' . $class_completion_report_link . '">' . $class_completion_report_name . '</a></li>';

        echo '</ul>';
    }
}
