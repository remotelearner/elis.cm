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

global $CFG;
require_once ($CFG->dirroot.'/course/lib.php');
require_once elis::lib('data/data_object.class.php');
require_once elispm::lib('deprecatedlib.php');

define('MCULR_NEEDLE', '?id=');
define('MCULR_NEEDLE_LENGTH', 4);

/*
 * Used to display the browsing interface to select
 * a Moodle course (or enter a Moodle course URL) as
 * a CM course template.
 *
 * This class is currently a WIP
 *
 */

class moodlecourseurl {

    var $_templateType;
    var $_templateTypeString;
    var $_referenceTable;
    var $_templateValues = array();
    var $_referenceId;

    function __construct() {
        global $ME, $PAGE;

        $this->_templateType = 'moodlecourseurl';
        $this->_templateTypeString = get_string('moodlecourseurl', 'elis_program');
        $this->_referenceTable =  'course';

        $context = get_context_instance(CONTEXT_SYSTEM);
    }

    /**
     * Building of breadcrumbs for browsing window.
     *
     * @param int catid course category id
     * @param string navlinks a string to append these breakcrumbs to
     *
     * @return string the breadcrumb string
     */
    function buildNavLinks($catid, $navlinks) {
        global $CFG, $DB;
        $crumb = '';

        $category = $DB->get_record('course_categories', array('id'=>$catid));
        if (!empty($category)) {
            $parent = $DB->get_record('course_categories', array('id'=>$category->parent));
        }

        if (!empty($category)) {
            if (isset($parent) and false !== $parent) {
                $navlinks .= $this->buildNavLinks($parent->id, $navlinks);
            }
            $navlinks .= '<span class="accesshide " >/&nbsp;</span><span class="arrow sep">&#x25BA;</span>'.
                ' <li class="first"><a onclick="this.target=\'_top\'" href="'.
                $CFG->wwwroot.'/elis/program/coursetemplatepage.php?class='.$this->_templateType.
                '&category='.$category->id.'">'.htmlspecialchars($category->name).'</a></li><li class="first">';

        }

        return $navlinks;
    }

    function getSubCategories($parentcat) {
        global $DB;

        $categories = $DB->get_recordset('course_categories', array('parent'=>$parentcat));
        return !empty($categories) ? $categories : array();
    }

    function getCourses($catid) {
        global $DB;

        $courses = $DB->get_recordset('course', array('category'=>$catid));
        return $courses;
    }

    public function printCourses($courseObj) {
        global $CFG;
        // ELIS-8338 BJB130313: single quote must be backslashed but double quote must be changed to 'entity'
        $js_escaped_name = str_replace(array('\\', "'"), array('\\\\', "\'"), "{$courseObj->fullname} ({$courseObj->shortname})");
        $js_escaped_name = htmlentities($js_escaped_name);

        $html_escaped_name = htmlspecialchars($courseObj->fullname);
        $output = "-> <a name=\"template\" onClick=\"courseSelect({$courseObj->id}, '{$js_escaped_name}'); ".
                  "selectedCourse({$courseObj->id}, 'new'); self.close(); return false;\" id=\"{$courseObj->id}\" ".
                  "class=\"notselect\">{$html_escaped_name}</a><br />";
        return $output;
    }

    function printCategories($catObj, $subCat = false, $selected) {
        global $CFG;
        $html_escaped_name = htmlspecialchars($catObj->name);
        if ($subCat) {
            $output = "<a href=\"{$CFG->wwwroot}/elis/program/coursetemplatepage.php".
                      "?class={$this->_templateType}&category={$catObj->id}&selected={$selected}\">{$html_escaped_name}".
                      " (click to expand)</a><br />";
        } else {
            $output = $html_escaped_name;
        }
        return $output;
    }

    /**
     * Displays the browsing page
     *
     * @param int category course category id
     * @param int selected course id of previously selected course
     * @uses  $CFG
     * @uses  $ME
     * @uses  $PAGE
     * @uses  $OUTPUT
     */
    function displayPage($category = 0, $selected = 0) {
        global $CFG, $ME, $PAGE, $OUTPUT;

        $PAGE->requires->js('/elis/program/js/moodlecourseurl.js');

        if (!$site = get_site()) {
            print_error('site_not_defined', 'elis_program');
        }

        $strcourses = get_string('courses');
        $strcategories = get_string('categories');
        $navigation = array('newnav' => 1, 'navlinks' => '');

        // Build root breadcrumb
        $navigation['navlinks'] = '<li class="first"><a onclick="this.target=\'_top\'" href="'.
                $CFG->wwwroot.'/elis/program/coursetemplatepage.php?class='.$this->_templateType.
                '&selected='.$selected.'">Root</a></li><li class="first"> ';

        // Build breadcrumb of course subcategories
        $navigation['navlinks'] .= $this->buildNavLinks($category,'');

        /* *** TBD *** */
        $PAGE->set_url($ME);
        $PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
        //$PAGE->set_title($site->fullname);
        //$PAGE->set_heading($site->shortname);
        $PAGE->set_title(get_string('coursetemplate', 'elis_program'));
        $PAGE->set_heading(get_string('coursetemplate', 'elis_program'));
        $PAGE->set_pagelayout('popup');
        $PAGE->set_pagetype('elis');
        $PAGE->set_cacheable(true);
        $PAGE->set_button('');
        echo $OUTPUT->header();

        echo $navigation['navlinks'].'<br />';

        $categories = $this->getSubCategories($category);

        echo '<form name="moodlecourseurl">'."\n";
        foreach ($categories as $key => $category) {

            echo $this->printCategories($category, true, $selected);

            $courses = $this->getCourses($category->id);
            if ($courses->valid() === true) {
                foreach ($courses as $key2 => $course) {
                    echo $this->printCourses($course);
                }
            } else {
                echo get_string('no_courses', 'elis_program').'<br />';
            }
            unset($courses);
        }
        // Add call to highlight previously selected course
        echo '<script language=javascript >';
        echo 'selectedCourse('.$selected.', \'old\');';
        echo '</script>';

        echo $this->addCss();

        echo '</form>'."\n";

        echo '<br />';
        echo $OUTPUT->close_window_button();

        echo $OUTPUT->footer();
    }

    /**
     * NOTE: this function will soon be phased out as
     * a course url will no longer be used or allowed
     *
     * Extract the course id from the course view URL
     * Currently the only format that should be used
     * is http://.../course/view.php?id=x
     *
     * @param string $url URl to course view page
     */
    function parseCourseId($url) {
        $position = strpos($url, MCULR_NEEDLE);
        $courseid = false;

        if (false !== $position) {
            $courseid = substr($url, $position + MCULR_NEEDLE_LENGTH);
        }
        return $courseid;
    }

    private function addCss() {
        // Add custom style
        echo '<style type="text/css">
        .oldselect {background-color: yellow;}
        .newselect {background-color: #00CC33;}
        .logininfo {visibility: hidden;}
        </style>';
    }
}
