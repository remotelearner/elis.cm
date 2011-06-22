<?php
/**
 * A (fake) page for JasperServer reports.
 *
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once (CURMAN_DIRLOCATION . '/lib/newpage.class.php');
require_once (CURMAN_DIRLOCATION . '/lib/jasperlib.php');
require_once (CURMAN_DIRLOCATION . '/lib/cluster.class.php');

class jasperreportpage extends newpage {
    var $pagename = 'rpt';
    var $section = 'rept';

    static $reports = array(
        'induser' => array('type' => 'user'),
        'usersreport' => array('type' => 'cluster'),
        'Course_Completion_By_Cluster_New' => array('type' => 'cluster'),
        'curricula' => array('type' => 'cluster'),
        //'Class_Activity_by_Course_Group' => array('type' => 'admin'),
        //'Class_Activity_Report_with_Role_Parameter' => array('type' => 'admin'),
        'Course_Completion_Gas_Gauge' => array('type' => 'admin'),
        'Forum_Participation' => array('type' => 'admin'),
        'New_Registrants_by_Student' => array('type' => 'admin'),
        'New_Registrants_Grouped_by_Course' => array('type' => 'admin'),
        'Non-Starter_Report' => array('type' => 'admin'),
        'Outcomes_New' => array('type' => 'admin'),
        'Site_Wide_Course_Completion_Report' => array('type' => 'admin'),
        'Site_Wide_ELIS_Transcript_Report' => array('type' => 'admin'),
        'sitewide_time_summary'=> array('type' => 'admin'),
        );

    function __construct($params=false) {
        if (is_array($params) && isset($params['action']) && ($params['action'] == 'reportslist' || $params['action'] == 'adhoc')) {
            $this->section = 'admn';
        }
        parent::__construct($params);
    }

    function can_view_admin() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:viewreports', $context);
    }

    function can_view_cluster() {
        global $USER;
        $context = get_context_instance(CONTEXT_SYSTEM);
        $crlm_uid = cm_get_crlmuserid($USER->id);
        if (empty($crlm_uid)) {
            $crlm_uid = -1;
        }
        return $this->can_view_admin()
            || has_capability('block/curr_admin:viewgroupreports', $context)
            || record_exists(CLSTASSTABLE, 'userid', $crlm_uid, 'leader', 1);
    }

    function can_view_user() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return $this->can_view_cluster()
            || has_capability('block/curr_admin:viewownreports', $context);
    }

    function can_do_default() {
        if (!isset($this->params['report'])) {
            return false;
        }
        return call_user_func(array($this, 'can_view_' . jasperreportpage::$reports[$this->params['report']]['type']));
    }

    function can_do_reportslist() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:managecurricula', $context);
    }

    function can_do_adhoc() {
        $context = get_context_instance(CONTEXT_SYSTEM);
        return has_capability('block/curr_admin:managecurricula', $context);
    }

    static $js_pages = array(
        'reportslist' => '/flow.html?_flowId=listReportsFlow',
        'adhoc' => '/flow.html?_flowId=adhocFlow',
        );

    function get_moodle_url() {
        if (isset($this->params['report'])) {
            $reportdata = jasperreportpage::$reports[$this->params['report']];
            if (isset($this->params['id'])) {
                // only handle user forms for now
                if ($reportdata['type'] == 'user') {
                    return new moodle_url(jasper_mnet_link(jasper_report_link($this->params['report'],array('userid'=>$this->params['id']))));
                }
            }
            return new moodle_url(jasper_mnet_link(jasper_report_link($this->params['report'])));
        } else if (isset(jasperreportpage::$js_pages[$this->params['action']])) {
            return new moodle_url(jasper_mnet_link(jasperreportpage::$js_pages[$this->params['action']]));
        } else {
            return newpage::get_moodle_url();
        }
    }
}

?>
