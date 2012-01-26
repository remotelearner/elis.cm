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

global $CFG;
require_once ($CFG->dirroot.'/course/lib.php');
require_once CURMAN_DIRLOCATION . '/lib/datarecord.class.php';

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

    function moodlecourseurl() {
        $this->_templateType = get_string('moodlecourseurlclassname', 'block_curr_admin');
        $this->_templateTypeString = get_string('moodlecourseurl', 'block_curr_admin');
        $this->_referenceTable =  'course';
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
        global $CFG;
        $crumb = '';
        
        $category = get_record('course_categories', 'id', $catid);
        if (!empty($category)) {
            $parent = get_record('course_categories', 'id', $category->parent);
        }

        if (!empty($category)) {
            if (isset($parent) and false !== $parent) {
                $navlinks .= $this->buildNavLinks($parent->id, $navlinks);
            }
            $navlinks .= '<span class="accesshide " >/&nbsp;</span><span class="arrow sep">&#x25BA;</span>'.
                ' <li class="first"><a onclick="this.target=\'_top\'" href="'.
                $CFG->wwwroot.'/curriculum/coursetemplatepage.php?class='.$this->_templateType.
                '&category='.$category->id.'">'.htmlspecialchars($category->name).'</a></li><li class="first">';

        }

        return $navlinks;
    }
    
    function getSubCategories($parentcat) {
        $categories = get_records('course_categories', 'parent', $parentcat);
        return !empty($categories) ? $categories : array();
    }
    
    function getCourses($catid) {
        $courses = get_records('course', 'category', $catid);
        return $courses;
    }
    
    function printCourses($courseObj) {
        global $CFG;
        $js_escaped_name =  str_replace("'", "\\'", "{$courseObj->fullname} ({$courseObj->shortname})");
        $html_escaped_name = htmlspecialchars($courseObj->fullname);
        $output = "-> <a name=\"template\" ".
                  "onClick=\"courseSelect({$courseObj->id}, '$js_escaped_name');".
                  "selectedCourse({$courseObj->id}, 'new'); self.close(); return false;\"".
                  "id=\"{$courseObj->id}\" class=\"notselect\">$html_escaped_name</a><br />";
        return $output;
    }

    function printCategories($catObj, $subCat = false, $selected) {
        global $CFG;
        $html_escaped_name = htmlspecialchars($catObj->name);
        if ($subCat) {
            $output = "<a href=\"{$CFG->wwwroot}/curriculum/coursetemplatepage.php".
                      "?class={$this->_templateType}&category={$catObj->id}&selected=$selected\">$html_escaped_name".
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
     */
    function displayPage($category = 0, $selected = 0) {
        global $CFG;
        require_js($CFG->wwwroot . '/curriculum/js/moodlecourseurl.js');

        if (!$site = get_site()) {
            error('Site isn\'t defined!');
        }

        $strcourses = get_string('courses');
        $strcategories = get_string('categories');
        $navigation = array('newnav' => 1, 'navlinks' => '');
        
        // Build root breadcrumb
        $navigation['navlinks'] = '<li class="first"><a onclick="this.target=\'_top\'" href="'.
                $CFG->wwwroot.'/curriculum/coursetemplatepage.php?class='.$this->_templateType.
                '&selected='.$selected.'">Root</a></li><li class="first"> ';

        // Build breadcrumb of course subcategories
        $navigation['navlinks'] .= $this->buildNavLinks($category,'');

        print_header_simple($site->fullname, $site->shortname, $navigation, '', '', true, '', false, '', true);
        $categories = $this->getSubCategories($category);

        echo '<form name="moodlecourseurl">'."\n";
        foreach ($categories as $key => $category) {

            echo $this->printCategories($category, true, $selected);

            $courses = $this->getCourses($category->id);
            $courses = (!empty($courses)) ? $courses : array();

            foreach ($courses as $key2 => $course) {
                echo $this->printCourses($course);
            }
            if (empty($courses)) {
                echo get_string('no_courses', 'block_curr_admin');
            }

        }
        // Add call to highlight previously selected course 
        echo '<script language=javascript >';
        echo 'selectedCourse('.$selected.', \'old\');';
        echo '</script>';

        echo $this->addCss();
        
        echo '</form>'."\n";
        
        echo '<br />';
        close_window_button();
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
        .oldselect {background-color: yellow}
        .newselect {background-color: #00CC33}
        .logininfo {visibility: hidden}
        </style>';
    }
}
?>
