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
require_once dirname(__FILE__) . '/lib/setup.php';

/// This is the main entry point for the program management system. It can be called from anywhere.
/// By using this, we can manage all application-specific handling easier.

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

$pages = array(
    // Learning Plan
    'crscat' => array('class' => 'coursecatalogpage',
                      'file' => 'coursecatalogpage.class.php'),
    'certlist' => array('class' => 'certificatelistpage',
                        'file' => 'certificatelistpage.class.php'),

    // Information elements
    'tag' => array('class' => 'tagpage',
                   'file' => 'tagpage.class.php'),
    'env' => array('class' => 'envpage',
                   'file' => 'envpage.class.php'),

    // Manage Users
    'usr' => array('class' => 'userpage',
                   'file' => 'userpage.class.php'),
    'stucur' => array('class' => 'studentcurriculumpage',
                      'file' => 'curriculumstudentpage.class.php'),
    'usrclst' => array('class' => 'userclusterpage',
                       'file' => 'enrol/userset/manual/usersetassignmentpage.class.php'),
    'usrtrk' => array('class' => 'usertrackpage',
                      'file' => 'usertrackpage.class.php'),
    'usrrole' => array('class' => 'user_rolepage',
                       'file' => 'rolepage.class.php'),

    // Manage Clusters
    'clst' => array('class' => 'usersetpage',
                    'file' => 'usersetpage.class.php'),
    'clstsub' => array(
        'class' => 'usersetsubusersetpage',
        'file' => 'usersetpage.class.php'
    ),
    'clstcur' => array('class' => 'clustercurriculumpage',
                       'file' => 'clustercurriculumpage.class.php'),
    'clsttrk' => array('class' => 'clustertrackpage',
                       'file' => 'clustertrackpage.class.php'),
    'clstusr' => array('class' => 'clusteruserpage',
                       'file' => 'enrol/userset/manual/usersetassignmentpage.class.php'),
    'clstrole' => array('class' => 'cluster_rolepage',
                        'file' => 'rolepage.class.php'),
    'clstusrsel' => array('class' => 'clusteruserselectpage',
                          'file' => 'enrol/userset/manual/selectpage.class.php'),

    // Manage Curricula
    'cur' => array('class' => 'curriculumpage',
                   'file' => 'curriculumpage.class.php'),
    'curcrs' => array('class' => 'curriculumcoursepage',
                      'file' => 'curriculumcoursepage.class.php'),
    'currcrs' => array('class' => 'curriculumcoursepage',  // why do we have two different spellings?
                       'file' => 'curriculumcoursepage.class.php'),
    'curstu' => array('class' => 'curriculumstudentpage',
                      'file' => 'curriculumstudentpage.class.php'),
    'curclst' => array('class' => 'curriculumclusterpage',
                       'file' => 'clustercurriculumpage.class.php'),
    'curtag' => array('class' => 'curtaginstancepage',
                      'file' => 'taginstancepage.class.php'),
    'currole' => array('class' => 'curriculum_rolepage',
                       'file' => 'rolepage.class.php'),

    // Manage Tracks
    'trk' => array('class' => 'trackpage',
                   'file' => 'trackpage.class.php'),
    'trkm' => array('class' => 'trackpage', // why do we have two different spellings?
                    'file' => 'trackpage.class.php'),
    'trkusr' => array('class' => 'trackuserpage',
                      'file' => 'usertrackpage.class.php'),
    'trkclst' => array('class' => 'trackclusterpage',
                       'file' => 'clustertrackpage.class.php'),
    'trkcls' => array('class' => 'trackassignmentpage',
                      'file' => 'trackassignmentpage.class.php'),
    'trkrole' => array('class' => 'track_rolepage',
                       'file' => 'rolepage.class.php'),

    // Manage Courses
    'crs' => array('class' => 'coursepage',
                   'file' => 'coursepage.class.php'),
    'cfc' => array('class' => 'curriculumforcoursepage',
                   'file' => 'curriculumpage.class.php'),
    'crscurr' => array('class' => 'coursecurriculumpage',
                       'file' => 'curriculumcoursepage.class.php'),
    'crstag' => array('class' => 'crstaginstancepage',
                      'file' => 'taginstancepage.class.php'),
    'crsrole' => array('class' => 'course_rolepage',
                       'file' => 'rolepage.class.php'),
    'crsengine' => array('class' => 'course_enginepage',
                         'file' => 'resultspage.class.php'),
    'crsenginestatus' => array('class' => 'course_enginestatuspage',
                               'file' => 'resultsstatuspage.class.php'),

    // Manage Classes
    'cls' => array('class' => 'pmclasspage',
                   'file' => 'pmclasspage.class.php'),
    'ins' => array('class' => 'instructorpage',
                   'file' => 'instructorpage.class.php'),
    'stu' => array('class' => 'studentpage',
                   'file' => 'studentpage.class.php'),
    'wtg' => array('class' => 'waitlistpage',
                   'file' => 'waitlistpage.class.php'),
    'clstag' => array('class' => 'clstaginstancepage',
                      'file' => 'taginstancepage.class.php'),
    'clsrole' => array('class' => 'class_rolepage',
                       'file' => 'rolepage.class.php'),
    'clsengine' => array('class' => 'class_enginepage',
                         'file' => 'resultspage.class.php'),
    'clsenginestatus' => array('class' => 'class_enginestatuspage',
                               'file' => 'resultsstatuspage.class.php'),

    // Administration
    'replnk' => array('class' => 'class_reportlinkspage',
                      'file' => 'reportlinkspage.class.php'),
    'bulkuser' => array('class' => 'bulkuserpage',
                        'file' => 'bulkuserpage.class.php'),
	'resultsconfig' => array('class' => 'resultsconfigpage',
                        'file' => 'resultsconfigpage.class.php'),
    'field' => array('class' => 'customfieldpage',
                     'file' => 'customfieldpage.class.php'),
    'health' => array('class' => 'healthpage',
                      'file' => 'healthpage.class.php'),
    'clstclass' => array('class' => 'usersetclassificationpage',
                         'file' => 'plugins/userset_classification/usersetclassificationpage.class.php'),
    'dim' => array('class' => 'dataimportpage',
                   'file' => 'elis_ip/elis_ip_page.php'),
    'dftcls' => array('class' => 'configclsdefaultpage',
                      'file' => 'configclsdefaultpage.class.php'),
    'dftcrs' => array('class' => 'configcrsdefaultpage',
                      'file' => 'configcrsdefaultpage.class.php'),
    'ntf' => array('class' => 'notifications',
                   'file' => 'notificationspage.class.php'),

    // Course Requests
    'crp' => array(
        'class' => 'RequestPage',
        'file'  => '../../blocks/course_request/requestpage.php'
        ),
    'erp' => array(
        'class' => 'EditRequestPage',
        'file'  => '../../blocks/course_request/editrequestpage.php'
        ),
    'arp' => array(
        'class' => 'courserequestapprovepage',
        'file'  => '../../blocks/course_request/approvepage.class.php'
        )
);

$section = optional_param('s', '', PARAM_ACTION);

if (isset($pages[$section])) {
    include elispm::file($pages[$section]['file']);
    $classname = $pages[$section]['class'];
    $PAGE = new $classname();
} else {
    include elispm::file('dashboardpage.class.php');
    $PAGE = new dashboardpage();
}
$PAGE->requires->css('/elis/program/icons.css');
// ELIS-3042
if (empty($PAGE->nologin) && !isloggedin()) {
    redirect($CFG->wwwroot);
}

//calculate the path of curriculum entities corresponding to the most recent click
$currentitypath = optional_param('currentitypath', '', PARAM_TEXT);

if (!empty($currentitypath)) {
    //parameter is set directly, so use it
    $USER->currentitypath = $currentitypath;
} else {
    //determine whether to unset the parameter based on comparing the current
    //page type with the last entity set
    $effective_entity_type = $PAGE->get_page_context();

    if (isset($USER->currentitypath)) {
        $parts = explode('/', $USER->currentitypath);
        $final_part = $parts[count($parts) - 1];

        $parts = explode('-', $final_part);

        if ($parts[0] != $effective_entity_type) {
            unset($USER->currentitypath);
        }
    }
}

$PAGE->run();
