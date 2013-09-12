<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// ELIS Libs.
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));
require_once(elis::file('program/enrol/userset/moodle_profile/userset_profile.class.php'));
require_once(elis::file('core/fields/manual/custom_fields.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::file('form/cmform.class.php'));

/**
 * A test moodleform.
 */
class test_moodleform extends cmform {
    /**
     * Form definition.
     */
    public function definition() {
        if (!empty($this->_customdata['obj'])) {
            if (is_object($this->_customdata['obj']) && method_exists($this->_customdata['obj'], 'to_object')) {
                $this->_customdata['obj'] = $this->_customdata['obj']->to_object();
            }
            $this->set_data($this->_customdata['obj']);
        }
    }

    /**
     * Test-only method to give us access to _elements.
     */
    public function get_elements() {
        return $this->_form->_elements;
    }

    /**
     * Test-only method to give us access to _form.
     */
    public function get_mform() {
        return $this->_form;
    }
}

/**
 * Test curriculum custom fields.
 * @group elis_program
 */
class curriculumcustomfields_testcase extends elis_database_test {

    /**
     * Clear custom field cache lists.
     */
    protected function setUp() {
        parent::setUp();
        $classes = array('curriculum', 'track', 'course', 'pmclass', 'user', 'userset');
        foreach ($classes as $class) {
            $temp = new $class;
            $temp->reset_custom_field_list();
        }
    }

    /**
     * Create a custom field category.
     * @param int $context The context level constant to create the category for (ex. CONTEXT_ELIS_USER)
     * @return field_category The created field category.
     */
    protected function create_field_category($context) {
        $data = new stdClass;
        $data->name = context_elis_helper::get_class_for_level($context).' Test';

        $category = new field_category($data);
        $category->save();

        $categorycontext = new field_category_contextlevel();
        $categorycontext->categoryid = $category->id;
        $categorycontext->contextlevel = $context;
        $categorycontext->save();

        return $category;
    }

    /**
     * Create an ELIS custom field.
     * @param field_category &$cat The category to create the field in.
     * @param int $context The context level constant to create the category for (ex. CONTEXT_ELIS_USER)
     * @return field The created field.
     */
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

        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'manual';
        $owner->params = serialize(array(
            'required' => false,
            'edit_capability' => '',
            'view_capability' => '',
            'control' => 'text',
            'columns' => 30,
            'rows' => 10,
            'maxlength' => 2048,
            'startyear' => '1970',
            'stopyear' => '2038',
            'inctime' => '0'
        ));
        $owner->save();

        return $field;
    }

    /**
     * Create a moodle user custom field.
     * @param field_category $cat The category to create the field in.
     * @return field The created field.
     */
    public function create_user_field(field_category $cat) {
        global $DB;

        // Create category.
        $mcat = (object)array(
            'name' => $cat->name,
            'sortorder' => 1,
        );
        $mcat->id = $DB->insert_record('user_info_category', $mcat);

        // Create field.
        $field = (object)array(
            'shortname' => 'user_testfield',
            'name' => 'User Test Field',
            'datatype' => 'text',
            'description' => '',
            'descriptionformat' => 1,
            'categoryid' => $mcat->id,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => 1,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => 0
        );
        $field->id = $DB->insert_record('user_info_field', $field);

        $manualowneroptions = array(
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
        field_owner::ensure_field_owner_exists($field, 'manual', $manualowneroptions);
        $owner = new field_owner();
        $owner->fieldid = $field->id;
        $owner->plugin = 'moodle_profile';
        $owner->exclude = pm_moodle_profile::sync_to_moodle;
        $owner->save();

        return $field;
    }

    /**
     * Create an ELIS program.
     * @param field &$field A custom field to set when creating the program.
     * @return curriculum The created program.
     */
    public function create_curriculum(field &$field = null) {
        $data = new stdClass;
        $data->courseid = '';
        $data->idnumber = 'testprg';
        $data->name = 'Test Program';
        $data->description = '';
        $data->reqcredits = '';
        $data->priority = '0';
        $data->timetocomplete = '';
        $data->frequency = '';

        if (!empty($field)) {
            $fieldvar = 'field_'.$field->shortname;
            $data->$fieldvar = 'test field data';
        }

        $cur = new curriculum();
        $cur->set_from_data($data);
        $cur->save();
        return $cur;
    }

    /**
     * Create an ELIS track
     * @param curriculum &$cur An ELIS program to assign the track to.
     * @param field &$field A custom field to set when creating program.
     * @return track The created track.
     */
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

    /**
     * Create an ELIS course.
     * @param field &$field A custom field to set when creating the course.
     * @return course The created course.
     */
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

    /**
     * Create an ELIS class instance.
     * @param course &$course The course description to assign the class to.
     * @param field &$field A custom field to set when creating the class.
     * @return pmclass The created class.
     */
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

    /**
     * Create an ELIS user.
     * @param field $field A custom field to set when creating the user.
     * @return user The created user.
     */
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

    /**
     * Create an ELIS userset.
     * @param field $field A custom field to set when creating the userset.
     * @return userset The created userset.
     */
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

    /**
     * Dataprovider returning all ELIS custom context constants.
     */
    public function dataprovider_context_levels() {
        return array(
                array(CONTEXT_ELIS_PROGRAM),
                array(CONTEXT_ELIS_TRACK),
                array(CONTEXT_ELIS_COURSE),
                array(CONTEXT_ELIS_CLASS),
                array(CONTEXT_ELIS_USER),
                array(CONTEXT_ELIS_USERSET),
        );
    }

    /**
     * Test creating a custom field category for each context level.
     * @dataProvider dataprovider_context_levels
     */
    public function test_customfield_createcategory($contextlevel) {
        $category = $this->create_field_category($contextlevel);
        $this->assertNotEmpty($category->id);
    }

    /**
     * Test creating a custom field.
     * @dataProvider dataprovider_context_levels
     */
    public function test_curriculumcustomfield_create($contextlevel) {
        $category = $this->create_field_category($contextlevel);
        $field = $this->create_field($category, $contextlevel);

        $this->assertNotEmpty($field->id);
    }

    /**
     * Test creating a custom field and adding data.
     */
    public function test_curriculumcustomfield_adddata() {
        $category = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $field = $this->create_field($category, CONTEXT_ELIS_PROGRAM);
        $cur = $this->create_curriculum($field);

        $this->assertNotEmpty($cur->id);
    }

    /**
     * Test creating a custom field and adding data.
     */
    public function test_trackcustomfield_adddata() {
        $curcat = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $curfield = $this->create_field($curcat, CONTEXT_ELIS_PROGRAM);
        $cur = $this->create_curriculum($curfield);

        $trkcat = $this->create_field_category(CONTEXT_ELIS_TRACK);
        $trkfield = $this->create_field($trkcat, CONTEXT_ELIS_TRACK);
        $trk = $this->create_track($cur, $trkfield);

        $this->assertNotEmpty($trk->id);
    }

    /**
     * Test creating a custom field and adding data.
     */
    public function test_coursecustomfield_adddata() {
        $category = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $field = $this->create_field($category, CONTEXT_ELIS_COURSE);
        $course = $this->create_course($field);

        $this->assertNotEmpty($course->id);
    }

    /**
     * Test creating a custom field and adding data.
     */
    public function test_classcustomfield_adddata() {
        $crscat = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $crsfield = $this->create_field($crscat, CONTEXT_ELIS_COURSE);
        $crs = $this->create_course($crsfield);

        $clscat = $this->create_field_category(CONTEXT_ELIS_COURSE);
        $clsfield = $this->create_field($clscat, CONTEXT_ELIS_COURSE);
        $cls = $this->create_class($crs, $clsfield);

        $this->assertNotEmpty($cls->id);
    }

    /**
     * Test creating a custom field and adding data.
     */
    public function test_usercustomfield_adddata() {
        $category = $this->create_field_category(CONTEXT_ELIS_USER);
        $field = $this->create_field($category, CONTEXT_ELIS_USER);
        $user = $this->create_user($field);

        $this->assertNotEmpty($user->id);
    }

    /**
     * Test creating a custom field and adding data.
     */
    public function test_usersetcustomfield_adddata() {
        $category = $this->create_field_category(CONTEXT_ELIS_USERSET);
        $field = $this->create_field($category, CONTEXT_ELIS_USERSET);
        $usrset = $this->create_userset($field);

        $this->assertNotEmpty($usrset->id);
    }

    /**
     * ELIS-4797: Test Various Custom Field Operations
     */
    public function test_customfieldoperations() {
        $contextlevels = context_elis_helper::get_legacy_levels();
        foreach ($contextlevels as $ctxname => $ctxlvl) {

            $category = $this->create_field_category($ctxlvl);
            $field = $this->create_field($category, $ctxlvl);

            $fieldsfetched = field::get_for_context_level($ctxlvl);
            $fieldfound = false;
            foreach ($fieldsfetched as $fieldfetched) {
                if ($fieldfetched->shortname === $field->shortname) {
                    $fieldfound = true;
                }
            }
            $this->assertTrue($fieldfound);

            $fieldfetched = field::get_for_context_level_with_name($ctxlvl, $field->shortname);
            $this->assertEquals($field->shortname, $fieldfetched->shortname);

            $fieldfetched = field::ensure_field_exists_for_context_level($field, $ctxlvl, $category);
            $this->assertEquals($field->shortname, $fieldfetched->shortname);

            $catsfetched = field_category::get_for_context_level($ctxlvl);
            $catfound = false;
            foreach ($catsfetched as $catfetched) {
                if ($catfetched->id == $category->id) {
                    $catfound = true;
                }
            }
            $this->assertTrue($catfound);

            if ($ctxlvl === CONTEXT_ELIS_PROGRAM) {
                $cur = $this->create_curriculum();
                $fielddata = field_data::get_for_context_and_field(null, $field);
                $fielddata = $fielddata->current();
                $res = $fielddata->set_for_context_from_datarecord($ctxlvl, $cur);
                $this->assertTrue($res);
            }
        }
    }

    /**
     * ELIS-4797: Test Moodle Profile Custom Field Operations
     */
    public function test_moodleprofile_customfieldoperations() {
        $category = $this->create_field_category(CONTEXT_ELIS_USER);
        $field = $this->create_user_field($category);

        sync_profile_field_to_moodle($field);
        sync_profile_field_from_moodle($field);
    }

    /**
     * Test validate_custom_fields function.
     */
    public function test_validatecustomfields() {
        // Get a form.
        $frm = new test_moodleform();

        $contextlevels = context_elis_helper::get_legacy_levels();
        foreach ($contextlevels as $ctxname => $ctxlvl) {
            $category = $this->create_field_category($ctxlvl);
            $field = $this->create_field($category, $ctxlvl);
            switch($ctxname) {
                case 'curriculum':
                    // Create a curriculum.
                    $cur = $this->create_curriculum($field);
                    // Set up a dummy custom field with a default value.
                    $testvalue = array('id' => $cur->id, 'field_'.$field->shortname => '');
                    break;
                case 'track':
                    // Create a track.
                    $trk = $this->create_track($cur, $field);
                    // Set up a dummy custom field with a default value.
                    $testvalue = array('id' => $trk->id, 'field_'.$field->shortname => '');
                    break;
                case 'course':
                    // Create a course.
                    $crs = $this->create_course($field);
                    // Set up a dummy custom field with a default value.
                    $testvalue = array('id' => $crs->id, 'field_'.$field->shortname => '');
                    break;
                case 'class':
                    // Create a class.
                    $cls = $this->create_class($crs, $field);
                    // Set up a dummy custom field with a default value.
                    $testvalue = array('id' => $cls->id, 'field_'.$field->shortname => '');
                    break;
                case 'user':
                    // Create a user.
                    $user = $this->create_user($field);
                    // Set up a dummy custom field with a default value.
                    $testvalue = array('id' => $user->id, 'field_'.$field->shortname => '');
                    break;
                case 'cluster':
                    // Create a userset.
                    $userset = $this->create_userset($field);
                    // Set up a dummy custom field with a default value.
                    $testvalue = array('id' => $userset->id, 'field_'.$field->shortname => '');
                    break;
            }

            $return = $frm->validate_custom_fields($testvalue, $ctxname);
            $this->assertEmpty($return);
        }
    }

    /**
     * Test invalid context.
     * @expectedException coding_exception
     */
    public function test_validatecustomfields_invalidcontext() {
        // Get a form.
        $frm = new test_moodleform();
        $category = $this->create_field_category(CONTEXT_ELIS_PROGRAM);
        $field = $this->create_field($category, CONTEXT_ELIS_PROGRAM);

        // Create a curriculum.
        $cur = $this->create_curriculum($field);

        // Set up a dummy custom field with a default value.
        $testvalue = array('id' => $field->id, 'field_'.$field->shortname => '');

        // Test with an invalid context using the last test value.
        $frm->validate_custom_fields($testvalue, 'moodle_course');
    }
}
