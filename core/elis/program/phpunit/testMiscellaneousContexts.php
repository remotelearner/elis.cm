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
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));
ini_set('error_reporting',1);
ini_set('display_errors',1);

class curriculumCustomFieldsTest extends elis_database_test {
//     protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
		return array(
            clusterassignment::TABLE => 'elis_program',
            clustercurriculum::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            curriculumcourse::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_int::TABLE => 'elis_core',
            field_data_num::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            instructor::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            classmoodlecourse::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            student_grade::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
            userset_profile::TABLE  => 'elis_program',
            usertrack::TABLE => 'elis_program',
            waitlist::TABLE => 'elis_program',
            'block_instances' => 'moodle',
            'block_positions' => 'moodle',
            'cache_flags' => 'moodle',
            'cohort_members' => 'moodle',
            'config_plugins' => 'moodle',
            'course' => 'moodle',
            'comments' => 'moodle',
            'context' => 'moodle',
            'external_services_users' => 'moodle',
            'external_tokens' => 'moodle',
            'events_queue' => 'moodle',
            'events_queue_handlers' => 'moodle',
            'filter_active' => 'moodle',
            'filter_config' => 'moodle',
            'grading_areas' => 'moodle',
            'groupings' => 'moodle',
            'groups_members' => 'moodle',
            'log' => 'moodle',
            'rating' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'role_context_levels' => 'moodle',
            'role_names' => 'moodle',
            'sessions' => 'moodle',
            'user' => 'moodle',
            'user_enrolments' => 'moodle',
            'user_info_data' => 'moodle',
            'user_lastaccess' => 'moodle',
            'user_preferences' => 'moodle',
        );
    }

    protected function setUp() {
        parent::setUp();
        $this->importContextsTable();
    }

    private function setUpContextsTable() {
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);
    }

    protected function setUpUsersets() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function setUpCurriculum() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/curriculum.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function setUpCurriculumCourse() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function setUpCurriculumStudent() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumstudent::TABLE, elis::component_file('program', 'phpunit/curriculum_student.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }


    protected function setUpUsers() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

    }

    protected function setUpUserCustomFields() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/user_field.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function setUpCourses() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function setUpClasses() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    private function importContextsTable() {
        global $SITE;

        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        $site = self::$origdb->get_record('course', array('id' => $SITE->id));
        self::$overlaydb->import_record('course', $site);


        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,'instanceid' => $SITE->id));
        self::$overlaydb->import_record('context', $sitecontext);

        $elis_contexts = context_elis_helper::get_all_levels();
        foreach ($elis_contexts as $context_level) {
            $dbfilter = array('contextlevel' => $context_level);
            $recs = self::$origdb->get_records('context', $dbfilter);
            foreach ($recs as $rec) {
                self::$overlaydb->import_record('context', $rec);
            }
        }
    }

    private function setUpRolesTables() {
        $roles = self::$origdb->get_records('role');
        foreach ($roles as $rolerec) {
            self::$overlaydb->import_record('role', $rolerec);
        }

        $roles_ctxs = self::$origdb->get_records('role_context_levels');
        foreach ($roles_ctxs as $role_ctx) {
            self::$overlaydb->import_record('role_context_levels', $role_ctx);
        }
    }

    protected function create_field($shortname, field_category &$cat, $context) {
        $data = new stdClass;
        $data->shortname = $shortname;
        $data->name = ' Test Field';
        $data->categoryid = $cat->id;
        $data->description = 'Test Field';
        $data->datatype = 'text';
        $data->forceunique = '0';
        $data->mform_showadvanced_last = 0;
        $data->multivalued = '0';
        $data->defaultdata = '';
        $data->manual_field_enabled = '1';
        $data->manual_field_edit_capability = '';
        $data->manual_field_view_capability = '';
        $data->manual_field_control = 'text';
        $data->manual_field_options_source = '';
        $data->manual_field_options = '';
        $data->manual_field_columns = 30;
        $data->manual_field_rows = 10;
        $data->manual_field_maxlength = 2048;

        $field = new field($data);
        $field->save();

        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid      = $field->id;
        $fieldcontext->contextlevel = $context;
        $fieldcontext->save();

        return $field;
    }

    protected function create_field_category($context) {
        $data = new stdClass;
        $data->name = context_elis_helper::get_class_for_level($context).' Test';

        $category = new field_category($data);
        $category->save();

        $categorycontext = new field_category_contextlevel();
        $categorycontext->categoryid   = $category->id;
        $categorycontext->contextlevel = $context;
        $categorycontext->save();

        return $category;
    }

    public function create_userset(field $field=null) {
        $usrset = new userset;
        $usrset->reset_custom_field_list();
        $usrset->name = 'Test User Set For Field'.str_replace('.','',microtime(true));
        $usrset->parent = '0';

        if (!empty($field)) {
            $fieldvar = 'field_'.$field->shortname;
            $usrset->$fieldvar='test field data';
        }

        $usrset->save();
        return $usrset;
    }

    public function create_curriculum(field &$field = null) {
        $data = new stdClass;
        $data->courseid='';
        $data->idnumber='testprg';
        $data->name='Test Program';
        $data->description='';
        $data->reqcredits='';
        $data->priority='0';
        $data->timetocomplete='';
        $data->frequency='';

        if (!empty($field)) {
            $fieldvar = 'field_'.$field->shortname;
            $data->$fieldvar='test field data';
        }

        $cur = new curriculum();
        $cur->set_from_data($data);
        $cur->save();
        return $cur;
    }

    /**
     * Covers:
     * elis/program/scripts/fix_cluster_orphans.php:62
     * @global type $DB
     */
    public function testFixClusterOrphans() {
        global $DB;
        $this->setUpUsersets();

        //create orphan usersets
        $DB->delete_records(userset::TABLE, array('id' => 1));

        //run
        require_once(elis::file('program/scripts/fix_cluster_orphans.php'));
    }

    /**
     * Covers:
     * elis/program/lib/data/track.class.php:291
     */
    public function testDeleteTrack () {
        $this->setUpCurriculum();

        $data = new stdClass;
        $data->curid=1;
        $data->idnumber='TRK1';
        $data->name='Track 1';
        $data->description='Track Description';
        $data->startdate=0;
        $data->enddate='0';

        $trk = new track();
        $trk->set_from_data($data);
        $trk->save();

        $trk = new track($trk->id);
        $trk->delete();
    }

    /**
     * Covers:
     * elis/program/lib/data/userset.class.php:334
     * elis/program/lib/data/userset.class.php:453
     * elis/program/lib/data/userset.class.php:561
     * elis/program/lib/data/userset.class.php:595
     * elis/program/lib/data/userset.class.php:616
     * elis/program/lib/data/userset.class.php:721
     * elis/program/lib/data/userset.class.php:755
     * elis/program/lib/data/userset.class.php:847
     * elis/program/lib/data/userset.class.php:901
     */
    public function testUsersetContexts() {
        global $USER, $DB;
        require_once(elispm::file('plugins/userset_classification/usersetclassification.class.php'));
        require_once(elispm::file('plugins/userset_classification/lib.php'));

        $this->setUpUsers();
        $this->setUpUsersets();
        $this->setUpRolesTables();

        //TEST elis/program/lib/data/userset.class.php:334
        $res = userset::get_allowed_clusters(1);

        //TEST elis/program/lib/data/userset.class.php:453
        $uss_filter = new usersubset_filter('id', new field_filter('id', $id));
        $res = $uss_filter->get_sql();

        //TEST
        // elis/program/lib/data/userset.class.php:561
        // elis/program/lib/data/userset.class.php:595
        // elis/program/lib/data/userset.class.php:616
        // elis/program/lib/data/userset.class.php:721
        // elis/program/lib/data/userset.class.php:755
        $cat = $this->create_field_category(CONTEXT_ELIS_USERSET);
        $field = $this->create_field(USERSET_CLASSIFICATION_FIELD,$cat,CONTEXT_ELIS_USERSET);
        $userset = $this->create_userset($field);

        //get a role to assign
        $roles_ctx = $DB->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_USERSET));
        foreach ($roles_ctx as $i => $role_ctx) {
            $roleid = $role_ctx->roleid;
        }

        //add userset_view capability to our role
        $userset_context = context_elis_userset::instance($userset->id);
        $rc = new stdClass;
        $rc->contextid = $userset_context->id;
        $rc->roleid = $roleid;
        $rc->capability = 'elis/program:userset_view';
        $rc->permission = 1;
        $rc->timemodified = time();
        $rc->modifierid = 0;
        $DB->insert_record('role_capabilities', $rc);
        $rc = new stdClass;
        $rc->contextid = $userset_context->id;
        $rc->roleid = $roleid;
        $rc->capability = 'elis/program:userset_enrol_userset_user';
        $rc->permission = 1;
        $rc->timemodified = time();
        $rc->modifierid = 0;
        $DB->insert_record('role_capabilities', $rc);

        //assign role
        $user  = new user(103);
        $muser = $user->get_moodleuser();
        $raid = role_assign($roleid, $muser->id, $userset_context->id);
        $user_id_backup = $USER->id;
        $USER->id = 100;

        //assign other user to userset
        $clst = new clusterassignment;
        $clst->clusterid = $userset->id;
        $clst->userid = 104;
        $clst->plugin = 'manual';
        $clst->save();

        //get cluster listing
        $capability = 'elis/program:userset_view';
        $contexts = get_contexts_by_capability_for_user('cluster', $capability, $USER->id);
        $extrafilters = array(
            'contexts' => $contexts,
            'classification' => 'test field data'
        );

        $res = cluster_get_listing('name','ASC',0,0,'','',$extrafilters,104);

        $res = cluster_count_records('','',$extrafilters);

        $USER->id = $user_id_backup;

        //TEST elis/program/lib/data/userset.class.php:847
        cluster_get_non_child_clusters(1);

        //TEST elis/program/lib/data/userset.class.php:901
        cluster_get_possible_sub_clusters(1);
    }

    /**
     * Covers:
     * elis/program/lib/data/user.class.php:169
     * elis/program/lib/data/user.class.php:673
     * elis/program/lib/data/user.class.php:1081
     * elis/program/lib/data/user.class.php:1291
     */
    public function testUserContexts() {
        global $DB;
        $this->setUpUsers();
        $user = new user(103);
        $user->delete();

        $this->setUpUsers();
        $this->setUpCurriculum();
        $this->setUpCurriculumStudent();
        $user = new user(103);
        // ELIS-5861 -- This method will write HTML to STDOUT, so wrap it in an output buffer
        ob_start();
        $user->get_dashboard();
        ob_end_clean();

        $this->setUpUserCustomFields();
        $fieldname = 'sometext';
        $rec = new field($DB->get_record(field::TABLE, array('shortname' => $fieldname)));
        $ufilter = new pm_custom_field_filter('field_'.$fieldname, $rec->shortname, $advanced, $rec);
        $sql = $ufilter->get_sql_filter(array());

        $ufiltering = new pm_user_filtering();
    }

    /**
     * Covers:
     * elis/program/lib/data/course.class.php:619
     */
    public function testCourseContexts() {
        $this->setUpCurriculum();
        $this->setUpCurriculumCourse();
        $this->setUpCourses();
        $crs = new course(100);
        $crs->delete();
    }

    /**
     * Covers:
     * elis/program/lib/data/pmclass.class.php:259
     */
    public function testClassContexts() {
        $this->setUpCurriculum();
        $this->setUpCurriculumCourse();
        $this->setUpCourses();
        $this->setUpClasses();

        $pmclass = new pmclass(101);
        $pmclass->delete();
    }

    /**
     * Covers:
     * elis/program/lib/data/curriculum.class.php:109
     */
    public function testCurriculumContexts() {
        $this->setUpCurriculum();
        $cur = new curriculum(1);
        $cur->delete();
    }

    /**
     * Covers:
     * elis/program/lib/notifications.php:616
     * elis/program/lib/notifications.php:617
     */
    public function testNotifications() {
        $this->setUpRolesTables();
        $this->setUpUsers();
        context_helper::reset_caches();

        elis::$config->elis_program->notify_classenrol_user = true;

        $roles_ctx = self::$overlaydb->get_records('role_context_levels',array('contextlevel' => CONTEXT_ELIS_PROGRAM));
        foreach ($roles_ctx as $role_ctx) {
            $roleid = $role_ctx->roleid;
            break;
        }

        //get user to assign role
        $user  = new user(103);
        $muser = $user->get_moodleuser();

        //get specific context
        $cur = $this->create_curriculum();
        $context = context_elis_program::instance($cur->id);

        //assign role
        $raid = role_assign($roleid, $muser->id, $context);
    }

    /**
     * Covers:
     * elis/program/plugins/userset_classification/usersetclassification.class.php:132
     */
    public function testUsersetClassification() {
        require_once(elispm::file('plugins/userset_classification/usersetclassification.class.php'));
        require_once(elispm::file('plugins/userset_classification/lib.php'));

        $cat = $this->create_field_category(CONTEXT_ELIS_USERSET);
        $field = $this->create_field(USERSET_CLASSIFICATION_FIELD,$cat,CONTEXT_ELIS_USERSET);
        $userset = $this->create_userset($field);

        $res = usersetclassification::get_for_cluster($userset);
    }

    /**
     * Covers:
     * elis/program/plugins/enrolment_role_sync/lib.php:101
     * elis/program/plugins/enrolment_role_sync/lib.php:150
     */
    public function testEnrolRoleSync() {
        require_once(elispm::file('plugins/enrolment_role_sync/lib.php'));

        set_config('student_role', 9, 'pmplugins_enrolment_role_sync');
        enrolment_role_sync::student_sync_role_set();

        set_config('instructor_role', 9, 'pmplugins_enrolment_role_sync');
        enrolment_role_sync::instructor_sync_role_set();
    }

    /**
     * Covers:
     * elis/program/plugins/userset_display_priority/lib.php:43
     */
    public function testUsersetDisplayPriority() {
        require_once(elispm::file('plugins/userset_display_priority/lib.php'));
        $select = '';
        $join = '';
        userset_display_priority_append_sort_data('id', $select, $join);
    }

    /**
     * Covers:
     * elis/program/plugins/userset_groups/lib.php:643
     * elis/program/plugins/userset_groups/lib.php:308
     * elis/program/plugins/userset_groups/lib.php:601
     */
    public function testUsersetGroups() {
        set_config('userset_groupings', true, 'pmplugins_userset_groups');
        $cat = $this->create_field_category(CONTEXT_ELIS_USERSET);

        $field = $this->create_field('userset_groupings',$cat,CONTEXT_ELIS_USERSET);
        $userset = $this->create_userset($field);
        userset_groups_grouping_helper($userset->id, $userset->name);

        $field = $this->create_field('userset_group',$cat,CONTEXT_ELIS_USERSET);
        $userset = $this->create_userset($field);
        userset_groups_userset_allows_groups($userset->id);
    }

}


/*
 * Not Tested:
 * elis/program/lib/data/curriculumstudent.class.php:77
 * elis/program/lib/data/coursetemplate.class.php:46
 * elis/program/lib/data/course.class.php:897
 * elis/program/lib/data/curriculumcourse.class.php:93
 * elis/program/lib/data/curriculumcourse.class.php:939
 * elis/program/lib/data/curriculumcourse.class.php:961
 * elis/program/plugins/userset_classification/usersetclassification.class.php:46
 */

/**
 * Should be tested in UI:
 * elis/program/usertrackpopup.php:63
 */