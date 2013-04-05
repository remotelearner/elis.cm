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
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elis::file('program/enrol/userset/moodle_profile/userset_profile.class.php'));
require_once(elis::file('core/fields/manual/custom_fields.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once elispm::file('form/cmform.class.php');
ini_set('error_reporting',1);
ini_set('display_errors',1);

class test_moodleform extends cmform {
    public function definition() {
        if(!empty($this->_customdata['obj'])) {
            if (is_object($this->_customdata['obj']) && method_exists($this->_customdata['obj'], 'to_object')) {
                $this->_customdata['obj'] = $this->_customdata['obj']->to_object();
            }
            $this->set_data($this->_customdata['obj']);
        }

    }

    //test-only method to give us access to _elements
    public function get_elements() {
        return $this->_form->_elements;
    }

    public function get_mform() {
        return $this->_form;
    }

//function validation($data, $files) {
//        return array();
//    }
}
class curriculumCustomFieldsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
		return array(
            'context' => 'moodle',
            'course' => 'moodle',
            'events_queue' => 'moodle',
            'events_queue_handlers' => 'moodle',
            'user' => 'moodle',
            'user_info_category' => 'moodle',
            'user_info_field' => 'moodle',
            'user_info_data' => 'moodle',
            userset_profile::TABLE => 'elis_program',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            curriculum::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            classmoodlecourse::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            field_owner::TABLE => 'elis_core',
            userset::TABLE => 'elis_program'
        );
	}

    protected function setUp() {
        parent::setUp();
        $this->setUpContextsTable();
    }

    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);


        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
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

    protected function create_field(field_category &$cat, $context) {
        $data = new stdClass;
        $data->shortname = context_elis_helper::get_class_for_level($context).'_testfield';
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

    public function create_user_field(field_category $cat) {
        if (!self::$overlaydb->get_record('user_info_category', array('id' => 1))) {
            $mcat = (object) array(
                'id' => 1,
                'name' => $cat->name,
                'sortorder' => 1,
            );
            self::$overlaydb->import_record('user_info_category', $mcat);
        }

        $field = (object) array(
            'shortname' => 'user_testfield',
            'name' => 'User Test Field',
            'datatype' => 'text',
            'description' => '',
            'descriptionformat' => 1,
            'categoryid' => 1,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => 1,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => 0
        );

        $fieldid = self::$overlaydb->get_field('user_info_field', 'id', array('shortname' => 'subject'));
        if (empty($fieldid)) {
            $fieldid = self::$overlaydb->insert_record('user_info_field', $field);
        }

        $manual_owner_options = array(
            'required' => 0,
            'edit_capability' => '',
            'view_capability' => '',
            'control' => 'text',
            'columns' => 30,
            'rows' => 10,
            'maxlength' => 100
        );

        $field = new field;
        $field->shortname = 'user_testfield';
        $field->name = 'User Test Field';
        $field->datatype = 'char';
        field::ensure_field_exists_for_context_level($field, CONTEXT_ELIS_USER, $cat);
        field_owner::ensure_field_owner_exists($field, 'manual', $manual_owner_options);
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'moodle_profile';
        $owner->exclude = pm_moodle_profile::sync_to_moodle;
        $owner->save();

        return $field;
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

    public function create_track(curriculum &$cur, field &$field) {
        $data = new stdClass;
        $data->curid=$cur->id;
        $data->idnumber='TRK1';
        $data->name='Track 1';
        $data->description='Track Description';
        $data->startdate=0;
        $data->enddate='0';

        $fieldvar = 'field_'.$field->shortname;
        $data->$fieldvar='test field data';

        $trk = new track();
        $trk->set_from_data($data);
        $trk->save();

        return $trk;
    }

    public function create_course(field &$field) {
        $data = new stdClass;
        $data->name='Test Course';
        $data->code = '';
        $data->idnumber='CRS1';
        $data->syllabus = '';
        $data->lengthdescription = '';
        $data->length = 0;
        $data->credits = '';
        $data->completion_grade = '0';
        $data->cost = '';
        $data->version = '';
        $data->templateclass = 'moodlecourseurl';
        $data->locationlabel = '';
        $data->location = '';
        $data->temptype = '';

        $fieldvar = 'field_'.$field->shortname;
        $data->$fieldvar='test field data';

        $crs = new course();
        $crs->set_from_data($data);
        $crs->save();
        return $crs;
    }

    public function create_class(course &$course, field &$field) {
        $data = new stdClass;

        $data->courseid = $course->id;
        $data->idnumber = 'CLS101';
        $data->startdate = 0;
        $data->enddate = 0;
        $data->starttime = 31603200;
        $data->endtime = 31603200;
        $data->maxstudents = 0;
        $data->moodleCourses = array('moodlecourseid' => '0');
        $data->enrol_from_waitlist = '0';
        $data->field_class_testfield = '';
        $data->starttimeminute = 61;
        $data->starttimehour = 61;
        $data->endtimeminute = 61;
        $data->endtimehour = 61;

        $fieldvar = 'field_'.$field->shortname;
        $data->$fieldvar='test field data';

        $cls = new pmclass();
        $cls->set_from_data($data);
        $cls->save();
        return $cls;
    }

    public function create_user(field $field) {
        $data = new stdClass;
        $data->idnumber = 'testuser1';
        $data->username = 'testuser1';
        $data->firstname = 'Test';
        $data->lastname = 'User';
        $data->email = 'test@example.com';
        $data->country = 'CA';
        $data->birthday = '';
        $data->birthmonth = '';
        $data->birthyear = '';
        $data->language = 'en';
        $data->inactive = '0';

        $fieldvar = 'field_'.$field->shortname;
        $data->$fieldvar='test field data';

        $usr = new user();
        $usr->set_from_data($data);
        $usr->save();
        return $usr;
    }

    public function create_userset(field $field) {
        $data = new stdClass;
        $data->name = 'Test User Set 123';
        $data->parent = '0';
        $data->profile_field1 = '0';
        $data->profile_field2 = '0';

        $fieldvar = 'field_'.$field->shortname;
        $data->$fieldvar='test field data';

        $usrset = new userset();
        $usrset->set_from_data($data);
        $usrset->save();
        return $usrset;
    }

    /*
     * ELIS-4732: Unit tests for custom track field data
     */
    public function testCurriculumCustomFieldCreateCategory() {
        $category = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $this->assertNotEmpty($category->id);
    }

    public function testCurriculumCustomFieldCreate() {
        $category = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $field = $this->create_field($category, CONTEXT_ELIS_PROGRAM);

        $this->assertNotEmpty($field->id);
    }

    public function testCurriculumCustomFieldAddData() {
        $category = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $field = $this->create_field($category, CONTEXT_ELIS_PROGRAM);
        $cur = $this->create_curriculum($field);

        $this->assertNotEmpty($cur->id);
    }

    /*
     * ELIS-4733: Unit tests for custom track field data
     */
    public function testTrackCustomFieldCreateCategory() {
        $category = $this->create_field_category(CONTEXT_ELIS_TRACK);
        $this->assertNotEmpty($category->id);
    }

    public function testTrackCustomFieldCreate() {
        $category = $this->create_field_category(CONTEXT_ELIS_TRACK);
        $field = $this->create_field($category, CONTEXT_ELIS_TRACK);

        $this->assertNotEmpty($field->id);
    }

    public function testTrackCustomFieldAddData() {
        $curcat = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $curfield = $this->create_field($curcat, CONTEXT_ELIS_PROGRAM);
        $cur = $this->create_curriculum($curfield);

        $trkcat = $this->create_field_category(CONTEXT_ELIS_TRACK);
        $trkfield = $this->create_field($trkcat, CONTEXT_ELIS_TRACK);

        $trk = $this->create_track($cur,$trkfield);

        $this->assertNotEmpty($trk->id);
    }

    /*
     * ELIS-4734: Unit tests for custom course description field data
     */
    public function testCourseFieldCreateCategory() {
        $category = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $this->assertNotEmpty($category->id);
    }

    public function testCourseCustomFieldCreate() {
        $category = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $field = $this->create_field($category, CONTEXT_ELIS_COURSE);

        $this->assertNotEmpty($field->id);
    }

    public function testCourseCustomFieldAddData() {
        $category = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $field = $this->create_field($category, CONTEXT_ELIS_COURSE);
        $course = $this->create_course($field);

        $this->assertNotEmpty($course->id);
    }

    /*
     * ELIS-4735: Unit tests for custom class instance field data
     */
    public function testClassFieldCreateCategory() {
        $category = $this->create_field_category(CONTEXT_ELIS_CLASS);
        $this->assertNotEmpty($category->id);
    }

    public function testClassCustomFieldCreate() {
        $category = $this->create_field_category(CONTEXT_ELIS_CLASS);
        $field = $this->create_field($category, CONTEXT_ELIS_CLASS);

        $this->assertNotEmpty($field->id);
    }

    public function testClassCustomFieldAddData() {
        //create our course
        $crscat = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $crsfield = $this->create_field($crscat,CONTEXT_ELIS_COURSE);
        $crs = $this->create_course($crsfield);

        $clscat = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $clsfield = $this->create_field($clscat, CONTEXT_ELIS_COURSE);
        $cls = $this->create_class($crs,$clsfield);

        $this->assertNotEmpty($cls->id);
    }

    /*
     * ELIS-4735: Unit tests for custom user field data
     */
    public function testUserFieldCreateCategory() {
        $category = $this->create_field_category(CONTEXT_ELIS_USER);
        $this->assertNotEmpty($category->id);
    }

    public function testUserCustomFieldCreate() {
        $category = $this->create_field_category(CONTEXT_ELIS_USER);
        $field = $this->create_user_field($category);

        $this->assertNotEmpty($field->id);
    }


    public function testUserCustomFieldAddData() {
        $category = $this->create_field_category(CONTEXT_ELIS_USER);
        $field = $this->create_field($category, CONTEXT_ELIS_USER);

        $user = $this->create_user($field);

        $this->assertNotEmpty($user->id);
    }

    /**
     * ELIS-4737: Unit tests for custom user set field data
     */
    public function testUsersetFieldCreateCategory() {
        $category = $this->create_field_category(CONTEXT_ELIS_USERSET);
        $this->assertNotEmpty($category->id);
    }

    public function testUsersetCustomFieldCreate() {
        $category = $this->create_field_category(CONTEXT_ELIS_USERSET);
        $field = $this->create_user_field($category);

        $this->assertNotEmpty($field->id);
    }

    public function testUsersetCustomFieldAddData() {
        $category = $this->create_field_category(CONTEXT_ELIS_USERSET);
        $field = $this->create_field($category, CONTEXT_ELIS_USERSET);

        $usrset = $this->create_userset($field);

        $this->assertNotEmpty($usrset->id);
    }

    /**
     * ELIS-4797: Test Various Custom Field Operations
     */
    public function testCustomFieldOperations() {
        $context_levels = context_elis_helper::get_legacy_levels();
        foreach ($context_levels as $ctxname=>$ctxlvl) {

            $category = $this->create_field_category($ctxlvl);
            $field = $this->create_field($category,$ctxlvl);

            $field_fetched = field::get_for_context_level($ctxlvl);
            $field_fetched = $field_fetched->current();
            $this->assertEquals($field->shortname,$field_fetched->shortname);

            $field_fetched = field::get_for_context_level_with_name($ctxlvl, $field->shortname);
            $this->assertEquals($field->shortname,$field_fetched->shortname);

            $field_fetched = field::ensure_field_exists_for_context_level($field, $ctxlvl, $category);
            $this->assertEquals($field->shortname,$field_fetched->shortname);

            $cat_fetched = field_category::get_for_context_level($ctxlvl);
            $cat_fetched = $cat_fetched->current();
            $this->assertEquals($category->id,$cat_fetched->id);

            if ($ctxlvl === CONTEXT_ELIS_PROGRAM) {
                $cur = $this->create_curriculum();
                $field_data = field_data::get_for_context_and_field(null,$field);
                $field_data = $field_data->current();
                $res = $field_data->set_for_context_from_datarecord($ctxlvl, $cur);
                $this->assertTrue($res);
            }
        }
    }

    /**
     *ELIS-4797: Test Moodle Profile Custom Field Operations
     */
    public function testMoodleProfileCustomFieldOperations() {
        $category = $this->create_field_category(CONTEXT_ELIS_USER);
        $field = $this->create_user_field($category);

        sync_profile_field_to_moodle($field);
        sync_profile_field_from_moodle($field);
    }


    public function testValidateCustomFields() {
        //get a form
        $frm = new test_moodleform();

        $context_levels = context_elis_helper::get_legacy_levels();
        foreach ($context_levels as $ctxname=>$ctxlvl) {
            $category = $this->create_field_category($ctxlvl);
            $field = $this->create_field($category,$ctxlvl);
            switch($ctxname) {
                case 'curriculum':
                    //create a curriculum
                    $cur = $this->create_curriculum($field);
                    //set up a dummy custom field with a default value
                    $test_value = array('id'=>$field->id,'field_'.$field->shortname=>$field->defaultvalue);
                    break;
                case 'track':
                    //create a track
                    $trk = $this->create_track($cur,$field);
                    //set up a dummy custom field with a default value
                    $test_value = array('id'=>$trk->id,'field_'.$field->shortname=>$field->defaultvalue);
                    break;
                case 'course':
                    //create a course
                    $crs = $this->create_course($field);
                    //set up a dummy custom field with a default value
                    $test_value = array('id'=>$crs->id,'field_'.$field->shortname=>$field->defaultvalue);
                    break;
                case 'class':
                    //create a class
                    $cls = $this->create_class($crs,$field);
                    //set up a dummy custom field with a default value
                    $test_value = array('id'=>$cls->id,'field_'.$field->shortname=>$field->defaultvalue);
                    break;
                case 'user':
                    //create a user
                    $user = $this->create_user($field);
                    //set up a dummy custom field with a default value
                    $test_value = array('id'=>$user->id,'field_'.$field->shortname=>$field->defaultvalue);
                    break;
                case 'cluster':
                    //create a userset
                    $userset = $this->create_userset($field);
                    //set up a dummy custom field with a default value
                    $test_value = array('id'=>$userset->id,'field_'.$field->shortname=>$field->defaultvalue);
                    break;

            }

            $return = $frm->validate_custom_fields($test_value,$ctxname);
            $this->assertEmpty($return);
        }
    }

    /**
     * Test invalid context
     *
     * @expectedException coding_exception
     */
    public function testValidateCustomFieldsInvalidContext() {
        //get a form
        $frm = new test_moodleform();
        $category = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $field = $this->create_field($category,CONTEXT_ELIS_PROGRAM);

        //create a curriculum
        $cur = $this->create_curriculum($field);
        //set up a dummy custom field with a default value
        $test_value = array('id'=>$field->id,'field_'.$field->shortname=>$field->defaultvalue);
        //test with an invalid context using the last test value
        $frm->validate_custom_fields($test_value,'moodle_course');
    }
}
