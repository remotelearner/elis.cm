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

require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

require_once($CFG->dirroot . '/user/profile/lib.php');

require_once(elispm::file('phpunit/datagenerator.php'));

class userTest extends elis_database_test {
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
            field::TABLE => 'elis_core',
            field_owner::TABLE => 'elis_core',
            field_category::TABLE => 'elis_core',
            field_data_int::TABLE => 'elis_core',
            field_data_num::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            course::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            curriculumcourse::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
        );
    }

    protected function setUp() {
        global $DB;
        parent::setUp();
        $DB = self::$origdb; // setUpContextsTable needs $DB to be the real
                             // database for get_admin()
        $this->setUpContextsTable();
        $DB = self::$overlaydb;
    }


    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        global $CFG;
        // system context
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        // site (front page) course
        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);
        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);

        // primary admin user
        $admin = get_admin();
        if ($admin) {
            self::$overlaydb->import_record('user', $admin);
            $CFG->siteadmins = $admin->id;
            $usercontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_USER,
                                                                      'instanceid' => $admin->id));
            self::$overlaydb->import_record('context', $usercontext);

            // copy admin user's ELIS user (if available)
            $elisuser = user::find(new field_filter('idnumber', $admin->idnumber), array(), 0, 0, self::$origdb);
            if ($elisuser->valid()) {
                $elisuser = $elisuser->current();
                self::$overlaydb->import_record(user::TABLE, $elisuser->to_object());
            }
        }
    }

    protected function load_csv_data() {
        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable('user_info_category', elis::component_file('program', 'phpunit/user_info_category.csv'));
        $dataset->addTable('user_info_field', elis::component_file('program', 'phpunit/user_info_field.csv'));
        $dataset->addTable('user_info_data', elis::component_file('program', 'phpunit/user_info_data.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset->addTable(field_category::TABLE, elis::component_file('program', 'phpunit/user_field_category.csv'));
        $dataset->addTable(field::TABLE, elis::component_file('program', 'phpunit/user_field.csv'));
        $dataset->addTable(field_owner::TABLE, elis::component_file('program', 'phpunit/user_field_owner.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        // load field data next (we need the user context ID and context level)
        $usercontext = context_elis_user::instance(103);
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_contextlevel::TABLE, elis::component_file('program', 'phpunit/user_field_contextlevel.csv'));
        $dataset->addTable(field_category_contextlevel::TABLE, elis::component_file('program', 'phpunit/user_field_category_contextlevel.csv'));
        $dataset->addTable(field_data_int::TABLE, elis::component_file('program', 'phpunit/user_field_data_int.csv'));
        //we don't have any num field data
        //$dataset->addTable(field_data_num::TABLE, elis::component_file('program', 'phpunit/user_field_data_num.csv'));
        $dataset->addTable(field_data_char::TABLE, elis::component_file('program', 'phpunit/user_field_data_char.csv'));
        $dataset->addTable(field_data_text::TABLE, elis::component_file('program', 'phpunit/user_field_data_text.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addFullReplacement('##USERCTXID##', $usercontext->id);
        $dataset->addFullReplacement('##USERCTXLVL##', CONTEXT_ELIS_USER);
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test that data class has correct DB fields
     */
    public function testDataClassHasCorrectDBFields() {
        $testobj = new user(false, null, array(), false, array(), self::$origdb);
        $this->assertTrue($testobj->_test_dbfields(), 'Error(s) with class $_dbfield_ properties.');
    }

    /**
     * Test that data class has correct associations
     */
    public function testDataClassHasCorrectAssociations() {
        $testobj = new user(false, null, array(), false, array(), self::$origdb);
        $this->assertTrue($testobj->_test_associations(), 'Error(s) with class associations.');
    }

    /**
     * Test that a record can be created in the database, and that a
     * corresponding Moodle user is modified.
     */
    public function testCanCreateRecordAndSyncToMoodle() {
        // create a record
        $src = new user(false, null, array(), false, array(), self::$overlaydb);
        $src->username = '_____phpunit_test_';
        $src->password = 'pass';
        $src->idnumber = '_____phpunit_test_';
        $src->firstname = 'John';
        $src->lastname = 'Doe';
        $src->mi = 'F';
        $src->email = 'jdoe@phpunit.example.com';
        $src->country = 'CA';
        $src->save();

        // map PM user fields to Moodle user fields
        $fields = array(
            'username' => 'username',
            'password' => 'password',
            'idnumber' => 'idnumber',
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'mi' => false,
            'email' => 'email',
            'country' => 'country',
        );

        // read it back
        $retr = new user($src->id, null, array(), false, array(), self::$overlaydb);
        foreach ($fields as $field => $notused) {
            $this->assertEquals($src->$field, $retr->$field);
        }

        // check that a Moodle user record was created
        $retr = self::$overlaydb->get_record('user', array('idnumber' => $src->idnumber), '*', MUST_EXIST);
        foreach ($fields as $pmfield => $mdlfield) {
            if ($mdlfield) {
                $this->assertEquals($src->$pmfield, $retr->$mdlfield);
            }
        }
    }

    /**
     * Test that a record can be modified, and that the corresponding Moodle
     * user is modified.
     */
    public function testCanUpdateRecordAndSyncToMoodle() {
        $this->load_csv_data();

        // read a record
        $src = new user(103, null, array(), false, array(), self::$overlaydb);
        $src->reset_custom_field_list();
        // modify the data
        $src->firstname = 'Testuser';
        $src->lastname = 'One';
        $src->field_sometext = 'boo';
        $src->field_sometextfrompm = 'bla';
        $src->save();

        // read it back
        $retr = new user($src->id, null, array(), false, array(), self::$overlaydb);
        $this->assertEquals($src->firstname, $retr->firstname);
        $this->assertEquals($src->lastname, $retr->lastname);

        // check the Moodle user
        $retr = self::$overlaydb->get_record('user', array('id' => 100)); // TBV
        profile_load_data($retr);
        $this->assertEquals($src->firstname, $retr->firstname);
        $this->assertEquals($src->lastname, $retr->lastname);

        // check custom fields
        $result = new moodle_recordset_phpunit_datatable('user_info_data', self::$overlaydb->get_records('user_info_data', null, '', 'id, userid, fieldid, data'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user_info_data', elis::component_file('program', 'phpunit/user_info_data.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        // only the second text field should be changed; everything else should
        // be the same
        $dataset->addFullReplacement('Second text entry field', 'bla');
        $this->assertTablesEqual($dataset->getTable('user_info_data'), $result);
    }

    /**
     * Test that creating a Moodle user also creates a corresponding PM user.
     */
    public function testCreatingMoodleUserCreatesPMUser() {
        // create a record
        $src = new stdClass;
        $src->username = '_____phpunit_test_';
        $src->password = 'pass';
        $src->idnumber = '_____phpunit_test_';
        $src->firstname = 'John';
        $src->lastname = 'Doe';
        $src->email = 'jdoe@phpunit.example.com';
        $src->country = 'CA';
        $src->id = self::$overlaydb->insert_record('user', $src);
        events_trigger('user_created', $src);

        // map PM user fields to Moodle user fields
        $fields = array(
            'username' => 'username',
            'password' => 'password',
            'idnumber' => 'idnumber',
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'mi' => false,
            'email' => 'email',
            'country' => 'country',
        );

        // read it back
        $retr = user::find(new field_filter('idnumber', $src->idnumber), array(), 0, 0, self::$overlaydb);
        $this->assertTrue($retr->valid());
        $retr = $retr->current();
        foreach ($fields as $pmfield => $mdlfield) {
            if ($mdlfield) {
                $this->assertEquals($src->$mdlfield, $retr->$pmfield);
            }
        }
    }

    /**
     * Test that modifying a Moodle user also updates the corresponding PM user.
     */
    public function testModifyingMoodleUserUpdatesPMUser() {
        $this->load_csv_data();

        // update a record
        $src = new stdClass;
        $src->id = 100;
        $src->firstname = 'Testuser';
        $src->lastname = 'One';
        $src->profile_field_sometext = 'boo';
        $src->profile_field_sometextfrompm = 'bla';
        self::$overlaydb->update_record('user', $src);
        $mdluser = self::$overlaydb->get_record('user', array('id' => 100));
        profile_save_data($src);
        events_trigger('user_updated', $mdluser);

        // read the PM user and compare
        $retr = new user(103, null, array(), false, array(), self::$overlaydb);
        $retr->reset_custom_field_list();
        $this->assertEquals($mdluser->firstname, $retr->firstname);
        $this->assertEquals($mdluser->lastname, $retr->lastname);

        // check custom fields
        $result = new PHPUnit_Extensions_Database_DataSet_DefaultDataSet();
        $result->addTable(new moodle_recordset_phpunit_datatable(field_data_int::TABLE, self::$overlaydb->get_recordset(field_data_int::TABLE, null, '', 'contextid, fieldid, data')));
        //$result->addTable(new moodle_recordset_phpunit_datatable(field_data_num::TABLE, self::$overlaydb->get_recordset(field_data_num::TABLE, null, '', 'contextid, fieldid, data'));
        $result->addTable(new moodle_recordset_phpunit_datatable(field_data_char::TABLE, self::$overlaydb->get_recordset(field_data_char::TABLE, null, '', 'contextid, fieldid, data')));
        $result->addTable(new moodle_recordset_phpunit_datatable(field_data_text::TABLE, self::$overlaydb->get_recordset(field_data_text::TABLE, null, '', 'contextid, fieldid, data')));
        $usercontext = context_elis_user::instance(103);
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(field_data_int::TABLE, elis::component_file('program', 'phpunit/user_field_data_int.csv'));
        //we don't have any num field data
        //$dataset->addTable(field_data_num::TABLE, elis::component_file('program', 'phpunit/user_field_data_num.csv'));
        $dataset->addTable(field_data_char::TABLE, elis::component_file('program', 'phpunit/user_field_data_char.csv'));
        $dataset->addTable(field_data_text::TABLE, elis::component_file('program', 'phpunit/user_field_data_text.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addFullReplacement('##USERCTXID##', $usercontext->id);
        $dataset->addFullReplacement('##USERCTXLVL##', CONTEXT_ELIS_USER);
        // only the first text field should be changed; everything else should
        // be the same
        $dataset->addFullReplacement('First text entry field', $src->profile_field_sometext);
        $ret = $dataset->addFullReplacement('Second text entry field', $src->profile_field_sometextfrompm); // TBD: ELIS-7616
        //var_dump($src);
        //var_dump($ret);
        $this->assertDataSetsEqual($dataset, $result);
    }

    public function test_get_dashboard_program_data() {
        global $DB;
        $datagen = new elis_program_datagen_unit($DB);

        //create entities
        $mock_user = $datagen->create_user();
        $pgm = $datagen->create_program();
        $pmcourse = $datagen->create_course();
        $pmclass = $datagen->create_pmclass(array('courseid'=>$pmcourse->id));

        //perform assignments
        $curcourse = $datagen->assign_course_to_curriculum($pmcourse->id,$pgm->id);
        $curstu = $datagen->assign_user_to_curriculum($mock_user->id,$pgm->id);
        $stu = $datagen->assign_user_to_class($mock_user->id,$pmclass->id);

        $expected_usercurs = array(
            $curstu->id => (object)array(
                'id' => $curstu->id,
                'curid' => $pgm->id,
                'name' => $pgm->name
            )
        );

        $expected_curriculas = array(
            $pgm->id => array(
                'id' => $pgm->id,
                'name' => $pgm->name,
                'data' => array(
                    array(
                        $pmcourse->name,
                        $pmclass->idnumber,
                        $pmcourse->syllabus,
                        0,
                        get_string('n_completed', 'elis_program'),
                        get_string('na','elis_program')
                    )
                )
            )
        );

        $expected_classids = array($pmclass->id);

        $expected_totalcurricula = 1;
        $expected_completecoursesmap = array($pgm->id => 0);
        $expected_totalcoursesmap = array($pgm->id => 1);

        $user = new user;
        $user->id = $mock_user->id;
        list($usercurs, $curriculas, $classids, $totalcurricula, $completecoursesmap, $totalcoursesmap) = $user->get_dashboard_program_data(false,false);
        $this->assertEquals($expected_usercurs,$usercurs);
        $this->assertEquals($expected_curriculas,$curriculas);
        $this->assertEquals($expected_classids,$classids);
        $this->assertEquals($expected_totalcurricula,$totalcurricula);
        $this->assertEquals($expected_completecoursesmap,$completecoursesmap);
        $this->assertEquals($expected_totalcoursesmap,$totalcoursesmap);


        $expected_curriculas = array();
        $expected_totalcurricula = 0;
        $expected_completecoursesmap = array();
        $expected_totalcoursesmap = array();
        list($usercurs, $curriculas, $classids, $totalcurricula, $completecoursesmap, $totalcoursesmap) = $user->get_dashboard_program_data(true,true);
        $this->assertEquals($expected_usercurs,$usercurs);
        $this->assertEquals($expected_curriculas,$curriculas);
        $this->assertEquals($expected_classids,$classids);
        $this->assertEquals($expected_totalcurricula,$totalcurricula);
        $this->assertEquals($expected_completecoursesmap,$completecoursesmap);
        $this->assertEquals($expected_totalcoursesmap,$totalcoursesmap);
    }
}
