<?php
/**
 * General class for displaying pages in the curriculum management system.
 *
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

require_once $CFG->dirroot . '/elis/core/lib/page.class.php';

abstract class newpage extends elis_page {
    var $pagename;

    /**
     * Prints the page header.
     */
    function print_header() {
        global $CFG, $USER, $PAGE;

        require_once($CFG->libdir.'/blocklib.php');
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/my/pagelib.php');


        /// My Moodle arguments:
        $edit        = optional_param('edit', -1, PARAM_BOOL);
        $blockaction = optional_param('blockaction', '', PARAM_ALPHA);

        $mymoodlestr = get_string('mymoodle','my');

        if (isguest()) {
            $wwwroot = $CFG->wwwroot.'/login/index.php';
            if (!empty($CFG->loginhttps)) {
                $wwwroot = str_replace('http:','https:', $wwwroot);
            }

            print_header($mymoodlestr);
            notice_yesno(get_string('noguest', 'my').'<br /><br />'.get_string('liketologin'),
                         $wwwroot, $CFG->wwwroot);
            print_footer();
            die();
        }

        /// Add curriculum stylesheets...
        if (file_exists($CFG->dirroot.'/curriculum/styles.css')) {
            $CFG->stylesheets[] = $CFG->wwwroot.'/curriculum/styles.css';
        }

        /// Fool the page library into thinking we're in My Moodle.
        $CFG->pagepath = $CFG->wwwroot.'/my/index.php';
        $PAGE = page_create_instance($USER->id);

        if ($section = optional_param('section', '', PARAM_ALPHAEXT)) {
            $PAGE->section = $section;
        }

        $this->pageblocks = blocks_setup($PAGE,BLOCKS_PINNED_BOTH);


        /// Make sure that the curriculum block is actually on this
        /// user's My Moodle page instance.
        if ($cablockid = get_field('block', 'id', 'name', 'curr_admin')) {
            if (!record_exists('block_pinned', 'blockid', $cablockid, 'pagetype', 'my-index')) {
                blocks_execute_action($PAGE, $this->pageblocks, 'add', (int)$cablockid, true, false);
            }
        }


        if (($edit != -1) and $PAGE->user_allowed_editing()) {
            $USER->editing = $edit;
        }

        //$PAGE->print_header($mymoodlestr);
        $title = $this->get_title();
        print_header($title, $title, build_navigation($this->get_navigation()));

        echo '<table border="0" cellpadding="3" cellspacing="0" width="100%" id="layout-table">';
        echo '<tr valign="top">';


        $blocks_preferred_width = bounded_number(180, blocks_preferred_width($this->pageblocks[BLOCK_POS_LEFT]), 210);

        if(blocks_have_content($this->pageblocks, BLOCK_POS_LEFT) || $PAGE->user_is_editing()) {
            echo '<td style="vertical-align: top; width: '.$blocks_preferred_width.'px;" id="left-column">';
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_LEFT);
            echo '</td>';
        }

        echo '<td valign="top" id="middle-column">';

        if (blocks_have_content($this->pageblocks, BLOCK_POS_CENTRE) || $PAGE->user_is_editing()) {
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_CENTRE);
        }
    }

    /**
     * Prints the page footer.
     */
    function print_footer() {
        global $PAGE;
        // Can only register if not logged in...
        echo '</td>';

        $blocks_preferred_width = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_RIGHT]), 210);

        if (blocks_have_content($this->pageblocks, BLOCK_POS_RIGHT) || $PAGE->user_is_editing()) {
            echo '<td style="vertical-align: top; width: '.$blocks_preferred_width.'px;" id="right-column">';
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_RIGHT);
            echo '</td>';
        }

        /// Finish the page
        echo '</tr></table>';
        print_footer();
    }

    /**
     * Returns the default title of the page.
     */
    function get_title_default() {
        return get_string('curriculummanagement', 'block_curr_admin');
    }

    /**
     * Returns the navigation links, as used by the Moodle build_header
     * function.  Do not override this method.  Instead, create
     * get_navigation_<action> methods.
     */
    function get_navigation() {
        $navigation = parent::get_navigation();
        global $CFG;
        array_unshift($navigation, array('name' => get_string('curriculummanagement', 'block_curr_admin'),
                                         'link' => $CFG->wwwroot . '/curriculum'));
        return $navigation;
    }

    /**
     * Return the URL for the base page.
     */
    public function get_base_url() {
        global $CFG;
        return $CFG->wwwroot . '/curriculum/index.php';
    }

    /**
     * Create a url to the current page.
     *
     * @return moodle_url
     */
    function get_moodle_url($extra = array()) {
        if (!isset($extra['s'])) {
            $extra['s'] = $this->pagename;
        }
        return parent::get_moodle_url($extra);
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
        } else if (isset($this->parent_page->data_class)) {
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

    /**
     * Specifies a unique shortname for the entity represented by
     * a page of this type, transforming the supplied value if necessary
     *
     * @param   string       $parent_path  Path of all parent elements, or the empty string
     *                                     if none
     * @param   string       $name         Initial name provided for the element
     *
     * @return  string|NULL                A valid name to identify the item with, or NULL if
     *                                     not applicable
     */
    static function get_entity_name($parent_path, $name) {
        //implement in child class, if necessary
        return NULL;
    }
}

?>
