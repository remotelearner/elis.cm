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


require_once ($CFG->dirroot . '/curriculum/lib/lib.php');
require_once ($CFG->dirroot . '/blocks/curr_admin/lib.php');
require_once ($CFG->dirroot . '/curriculum/lib/menuitem.class.php');
require_once ($CFG->dirroot . '/curriculum/lib/cluster.class.php');

/// Add curriculum stylesheets...
if (file_exists($CFG->dirroot.'/curriculum/styles.css')) {
//    echo '<link style="text/css" REL=StyleSheet HREF="' . $CFG->wwwroot . '/curriculum/styles.css" />';
    $CFG->stylesheets[] = $CFG->wwwroot . '/curriculum/styles.css';
}

class block_curr_admin extends block_base {

    var $currentdepth;
    var $spancounter;
    var $tempcontent;
    var $pathtosection;
    var $expandjavascript;
    var $destination;

    function init() {
        global $PAGE, $CURMAN;
        require_once CURMAN_DIRLOCATION.'/version.php';
        $this->title            = get_string('blockname', 'block_curr_admin');
        $this->version          = 2011050207;
        $this->release          = $CURMAN->release;
        $this->cron             = 300;
        $this->currentdepth     = 0;
        $this->spancounter      = 1;
        $this->tempcontent      = '';
        $this->section          = (isset($CURMAN->page->section) ? $CURMAN->page->section : (isset($PAGE->section) ? $PAGE->section : ''));
        $this->pathtosection    = array();
        $this->expandjavascript = '';
        $this->lastcron         = get_field('block', 'lastcron', 'name', 'curr_admin');
        $this->divcounter       = 1;
    }


    function applicable_formats() {
        if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
            return array(
                'my'         => true,
                'admin'      => true,
                'site-index' => true
            );
        } else {
            return array('my' => true);
        }
    }


    function preferred_width() {
        return 210;
    }


    /**
     * Are you going to allow multiple instances of each block?
     * If yes, then it is assumed that the block WILL USE per-instance configuration
     * @return boolean
     * @todo finish documenting this function by explaining per-instance configuration further
     */
    function instance_allow_multiple() {
        // Are you going to allow multiple instances of each block?
        // If yes, then it is assumed that the block WILL USE per-instance configuration
        return false;
    }


    function get_content() {
        global $CFG, $ADMIN, $USER, $CURMAN, $HTTPSPAGEREQUIRED;

        require_once($CFG->libdir . '/adminlib.php');
        require_once($CFG->dirroot . '/my/pagelib.php');

        // dependencies on page classes
        require_once($CFG->dirroot .'/curriculum/clusterpage.class.php');
        require_once($CFG->dirroot .'/curriculum/curriculumpage.class.php');
        require_once($CFG->dirroot .'/curriculum/coursepage.class.php');
        require_once($CFG->dirroot .'/curriculum/trackpage.class.php');

// ELIS-1251 - Don't display a useless message if in the center column
//        if ($this->instance->position == BLOCK_POS_CENTRE) {
//            $this->content = new stdClass;
//            $output = "This is content to display if in the middle...";
//            $this->content->text = $output;
//            $this->content->footer = '';
//            return $this->content;
//        }

    /// Display a link to the admin interface if on the main site index page and the current user has
    /// admin or developer access.
//        if ($this->instance->pageid == SITEID &&
//            has_capability('block/curr_admin:config', get_context_instance(CONTEXT_SYSTEM, SITEID))) {
//
//            $this->content = new stdClass;
//            $this->content->text   = '<a href="' . $CFG->wwwroot . '/curriculum/index.php">' .
//                                     get_string('accesscurriculumadmin', 'block_curr_admin') . '</a>';
//            $this->content->footer = '';
//        }

    /// Determine the users CM access level.
        $access = cm_determine_access($USER->id);
        $this->title = get_string("blocktitle{$access}", 'block_curr_admin');

        if (empty($access) || $this->content !== NULL) {
            return $this->content;
        }

        //if we are not on a CM "newpage", disable the expansion of
        //entities in the curr admin tree (logic in curriculum/index.php)
        if (!isset($CURMAN->page)) {
            unset($USER->currentitypath);
        }

        //include the necessary javascript libraries for the YUI TreeView
        require_js(array('yui_yahoo', 'yui_dom', 'yui_event', 'yui_treeview'));

        //for converting tree representation
        require_js('yui_json');

        //for asynch request dynamic loading
        require_js('yui_connection');

        //include our custom code that handles the YUI Treeview menu
        if (!empty($HTTPSPAGEREQUIRED)) {
            $wwwroot = $CFG->httpswwwroot;
        } else {
            $wwwroot = $CFG->wwwroot;
        }
        require_js($wwwroot . '/curriculum/js/menuitem.js');

        //CM entities for placement at the top of the menu
        $cm_entity_pages = array();
        $cm_entity_pages[] = new menuitem('root');

        $num_block_icons = isset($CURMAN->config->num_block_icons) ? $CURMAN->config->num_block_icons : 5;

        /*****************************************
         * Clusters
         *****************************************/
        if (!isset($CURMAN->config->display_clusters_at_top_level) || !empty($CURMAN->config->display_clusters_at_top_level)) {
            $manageclusters_css_class = block_curr_admin_get_item_css_class('manageclusters');
            $cluster_css_class = block_curr_admin_get_item_css_class('cluster_instance');

            require_once CURMAN_DIRLOCATION . '/lib/contexts.php';
            $context_result = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:cluster:view', $USER->id);
            $extrafilters = array('contexts' => $context_result, 'parent' => 0);
            $num_records = cluster_count_records('', '', $extrafilters);

            if ($clusters = cluster_get_listing('priority, name', 'ASC', 0, $num_block_icons, '', '', $extrafilters)) {
                foreach ($clusters as $cluster) {
                    $params = array('id'     => $cluster->id,
                                    'action' => 'view');

                    // count sub-clusters
                    $cluster_filter = array('contexts' => clusterpage::get_contexts('block/curr_admin:cluster:view'), 'parent' => $cluster->id);
                    $cluster_count = cluster_count_records('', '', $cluster_filter);

                    // count associated curricula
                    $curriculum_filter = array('contexts' => curriculumpage::get_contexts('block/curr_admin:curriculum:view'));
                    $curriculum_count = clustercurriculum::count_curricula($cluster->id, $curriculum_filter);

                    $isLeaf = empty($cluster_count) &&
                              empty($curriculum_count);

                    $cm_entity_pages[] = block_curr_admin_get_menu_item('cluster', $cluster, 'root', $manageclusters_css_class, $cluster->id, 0, $params, $isLeaf);
                }
            }

            if ($num_block_icons < $num_records) {
                $cm_entity_pages[] = block_curr_admin_get_menu_summary_item('cluster', $cluster_css_class, $num_records - $num_block_icons);
            }
        }

        /*****************************************
         * Curricula
         *****************************************/
        if (!empty($CURMAN->config->display_curricula_at_top_level)) {
            $managecurricula_css_class = block_curr_admin_get_item_css_class('managecurricula');
            $curriculum_css_class = block_curr_admin_get_item_css_class('curriculum_instance');

            require_once CURMAN_DIRLOCATION . '/curriculumpage.class.php';
            $num_records = curriculum_count_records('', '', curriculumpage::get_contexts('block/curr_admin:curriculum:view'));

            if ($curricula = get_records(CURTABLE, '', '', 'priority ASC, name ASC', '*', 0, $num_block_icons)) {
                foreach ($curricula as $curriculum) {
                    $params = array('id'     => $curriculum->id,
                                    'action' => 'view');

                    // count associated courses
                    $course_filter = array('contexts' => coursepage::get_contexts('block/curr_admin:course:view'));
                    $course_count = curriculumcourse_count_records($curriculum->id, '', '', $course_filter);

                    // count associated tracks
                    $track_contexts = trackpage::get_contexts('block/curr_admin:track:view');
                    $track_count = track_count_records('', '', $curriculum->id, 0, $track_contexts);

                    // count associated clusters
                    $cluster_filter = array('contexts' => clusterpage::get_contexts('block/curr_admin:cluster:view'));
                    $cluster_count = clustercurriculum::count_clusters($curriculum->id, 0, $cluster_filter);

                    $isLeaf = empty($course_count) &&
                              empty($track_count) &&
                              empty($cluster_count);

                    $cm_entity_pages[] = block_curr_admin_get_menu_item('curriculum', $curriculum, 'root', $managecurricula_css_class, 0, $curriculum->id, $params, $isLeaf);
                }
            }

            if ($num_block_icons < $num_records) {
                $cm_entity_pages[] = block_curr_admin_get_menu_summary_item('curriculum', $curriculum_css_class, $num_records - $num_block_icons);
            }
        }

        global $SITE;

        //general cm pages
        $pages = array(

                //Dashboard
                new menuitem('dashboard', new menuitempage('dashboardpage'), 'root', '', block_curr_admin_get_item_css_class('dashboard')),

                //Admin
                new menuitem('admn', null, 'root', get_string('admin'), block_curr_admin_get_item_css_class('admn', true)),
                new menuitem('bulkuser', new menuitempage('bulkuserpage'), null, get_string('userbulk', 'admin'), block_curr_admin_get_item_css_class('bulkuser')));

        //show the Jasper report server link if applicable
        if (cm_jasper_link_enabled()) {
            //page action
            $jasper_link_params = array('action' => 'reportslist');
            //page instance
            $jasper_link_page = new menuitempage('jasperreportpage', '', $jasper_link_params);
            //styling for the link
            $jasper_link_css = block_curr_admin_get_item_css_class('reportslist');

            $pages[] = new menuitem('reportslist', $jasper_link_page, null, '', $jasper_link_css);
        }

        $pages = array_merge($pages, array(
                new menuitem('customfields', new menuitempage('customfieldpage', '', array('level' => 'user')), null, '',
                             block_curr_admin_get_item_css_class('customfields')),
                new menuitem('clusterclassification',
                             new menuitempage('clusterclassificationpage', 'plugins/cluster_classification/clusterclassificationpage.class.php'),
                             null, get_string('cluster_classification', 'crlm_cluster_classification'), block_curr_admin_get_item_css_class('clusterclassification')),

                //Information Elements
                new menuitem('info', null, 'root', get_string('informationalelements', 'block_curr_admin'),
                             block_curr_admin_get_item_css_class('info', true)),
                new menuitem('managetags', new menuitempage('tagpage'), null, '',
                             block_curr_admin_get_item_css_class('managetags')),
                new menuitem('manageenvironments', new menuitempage('envpage'), null, '',
                             block_curr_admin_get_item_css_class('manageenvironments')),

                //Users
                new menuitem('users', null, 'root', '', block_curr_admin_get_item_css_class('users', true)),
                new menuitem('manageusers', new menuitempage('usermanagementpage'), null, '',
                             block_curr_admin_get_item_css_class('manageusers')),
                new menuitem('manageclusters', new menuitempage('clusterpage'), null, '',
                             block_curr_admin_get_item_css_class('manageclusters')),

                //Curriculum
                new menuitem('curr', null, 'root', get_string('curriculum', 'block_curr_admin'),
                             block_curr_admin_get_item_css_class('curr', true)),
                new menuitem('certificatelist', new menuitempage('certificatelistpage'), null, '',
                             block_curr_admin_get_item_css_class('certificatelist')),
                new menuitem('managecurricula', new menuitempage('curriculumpage'), null, '',
                             block_curr_admin_get_item_css_class('managecurricula')),
                new menuitem('managecourses', new menuitempage('coursepage'), null, '',
                             block_curr_admin_get_item_css_class('managecourses')),
                new menuitem('manageclasses', new menuitempage('cmclasspage'), null, '',
                             block_curr_admin_get_item_css_class('manageclasses')),

                //Learning Plan
                new menuitem('crscat', null, 'root', get_string('learningplan', 'block_curr_admin'),
                             block_curr_admin_get_item_css_class('crscat', true)),
                new menuitem('currentcourses', new menuitempage('coursecatalogpage', '', array('action' => 'current')), null, '',
                             block_curr_admin_get_item_css_class('currentcourses')),
                new menuitem('availablecourses', new menuitempage('coursecatalogpage', '', array('action' => 'available')), null, '',
                             block_curr_admin_get_item_css_class('availablecourses')),
                new menuitem('waitlist', new menuitempage('coursecatalogpage', '', array('action' => 'waitlist')), null,
                             get_string('waitlistcourses', 'block_curr_admin'), block_curr_admin_get_item_css_class('waitlist')),

                //Reports
                new menuitem('rept', null, 'root', get_string('reports', 'block_curr_admin'), block_curr_admin_get_item_css_class('rept', true))

        ));

        if (has_capability('moodle/course:managegroups', get_context_instance(CONTEXT_COURSE, $SITE->id))) {
            if ($CURMAN->config->site_course_cluster_groups) {
                $pages[] = new menuitem('frontpagegroups', new menuitempage('url_page', 'lib/menuitem.class.php', "{$CFG->wwwroot}/group/index.php?id={$SITE->id}"), 'admn', get_string('frontpagegroups', 'crlm_cluster_groups'), block_curr_admin_get_item_css_class('manageclusters'));
            }
            if ($CURMAN->config->cluster_groupings) {
                $pages[] = new menuitem('frontpagegroupings', new menuitempage('url_page', 'lib/menuitem.class.php', "{$CFG->wwwroot}/group/groupings.php?id={$SITE->id}"), 'admn', get_string('frontpagegroupings', 'crlm_cluster_groups'), block_curr_admin_get_item_css_class('manageclusters'));
            }
        }

        /**
         * This section adds all the necessary PHP reports to the menu
         */

        //get all report pages, including categories but not including the
        //topmost report element
        $report_pages = block_curr_admin_get_report_tree_items();

        //merge in the reporting page links
        $pages = array_merge($pages, $report_pages);

        if (empty($CURMAN->config->userdefinedtrack)) {
            $pages[] = new menuitem('managetracks', new menuitempage('trackpage'), null, '', block_curr_admin_get_item_css_class('managetracks'));
        }

        $access = cm_determine_access($USER->id);
        switch($access) {
            case 'admin':
            case 'developer':
                $pages[] = new menuitem('configmanager', new menuitempage('configpage', '', array('section' => 'admn')), null, get_string('configuration'), block_curr_admin_get_item_css_class('configuration'));
                $pages[] = new menuitem('notifications', new menuitempage('notifications', 'notificationspage.class.php', array('section' => 'admn')), null, '', block_curr_admin_get_item_css_class('notifications'));
                $pages[] = new menuitem('dataimport', new menuitempage('dataimportpage', 'elis_ip/elis_ip_page.php', array('section' => 'admn')), null, '', block_curr_admin_get_item_css_class('integrationpoint'));
                $pages[] = new menuitem('defaultcls', new menuitempage('configclsdefaultpage', '', array('section' => 'admn')), null, '', block_curr_admin_get_item_css_class('defaultcls'));
                $pages[] = new menuitem('defaultcrs', new menuitempage('configcrsdefaultpage', '', array('section' => 'admn')), null, '', block_curr_admin_get_item_css_class('defaultcrs'));
                break;
            default:
                break;
        }

        //turn all pages that have no children into leaf nodes
        menuitemlisting::flag_leaf_nodes($pages);

        //combine the specific entity page listing with the general CM listing
        $menuitemlisting = new menuitemlisting(array_merge($cm_entity_pages, $pages));
        $tree = new treerepresentation($menuitemlisting);

        $this->content = new stdClass;
        $this->content->text = $tree->convert_to_markup();
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Perform all the regularly scheduled tasks, such as grades updating and reporting.
     *
     */
    function cron() {
        global $CFG;

        require_once($CFG->dirroot . '/curriculum/lib/lib.php');

        $status = true;

        return cm_cron();
    }


    /**
     * Function that does extra setup after the database install. (Called once per block, not per instance!)
     */
    function after_install() {
        global $CFG, $db;
        $result = true;

        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);

        if (!record_exists('role', 'shortname', 'curriculumadmin')) {
            $roleid = create_role('Curriculum Administrator', 'curriculumadmin', 'Manage all curriculum functions.');
        }

        if (!empty($roleid)) {
            require_once dirname(__FILE__) . '/db/access.php';

            if (!empty($block_curr_admin_capabilities)) {
                foreach ($block_curr_admin_capabilities as $capname => $caprules) {
                    $result = $result && assign_capability($capname, CAP_ALLOW, $roleid, $context->id);
                }
            }
        }

        require_once($CFG->dirroot . '/curriculum/lib/lib.php');
        cm_migrate_moodle_users(true);

        /* create database views, needed for reports */
        //require_once($CFG->dirroot . '/blocks/curr_admin/lib.php');
        //$result = $result && create_views(); // create with default prefix
        //$result = $result && create_views(''); // create with no prefix

        set_config('field_lock_idnumber', 'locked', 'auth/manual');
    }


/**
 * Clean up added tables and roles when removing the block from the system.
 *
 * @uses $CFG
 * @uses $USER
 * @param none
 * @return none
 */
    function before_delete() {
        global $CFG, $USER, $db; // Needed for delete_role() API call.

    /// Delete tables.
        $tables = array(
            'crlm',
            'crlm_course',
            'crlm_userenrol'
        );

        foreach ($tables as $table) {
            $tbl = new XMLDBTable($table);

            if (table_exists($tbl)) {
                drop_table($tbl, true, false);
            }
        }

    /// Delete any capabilities we may have created during install.
        if (file_exists($CFG->dirroot . '/blocks/curr_admin/db/access.php')) {
            include($CFG->dirroot . '/blocks/curr_admin/db/access.php');

            if (!empty($block_curr_admin_capabilities)) {
                foreach ($block_curr_admin_capabilities as $cap => $rec) {
                    if (record_exists('capabilities', 'name', $cap)) {
                        delete_records('capabilities', 'name', $cap);
                    }
                }
            }
        }


    /// Remove the views we have created during install.
        $views = array(
            'GradesListing4Transcript',
            'UserExt',
            'Top5ForumUserTest',
            'Role2RoleAssignments',
            'Role2RoleAssignments5',
            'grade_grades_with_outcomes_ext',
            'grade_grades_with_outcome_counts',
            'GroupsNMembers',
            'moodleLog',
            'moodleLogwDate',
            'LogwDateSummary',
            'LogSummaryWithGroups',
            'LoginDurationByUserNDate',
            'LoginDurationByUserNDateExt',
            'testweekdayCalc',
            'LoginDurationByUserNDate',
            'LoginDurationByUserNDateExt',
            'LoginDurationbyUserNDate1',
            'LoginDurationByUserNDate1Ext',
            'SiteWideTimeStats',
            'courseNforums'
        );

        foreach ($views as $view) {
            if ($db->databaseType == 'postgres7') {
                execute_sql("DROP VIEW IF EXISTS $view CASCADE");
            } else {
                execute_sql("DROP VIEW IF EXISTS $view");
            }
        }
    }

}

?>
