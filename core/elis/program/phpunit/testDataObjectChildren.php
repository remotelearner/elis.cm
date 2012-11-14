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
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/classmoodlecourse.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/instructor.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/waitlist.class.php'));

class testDataObjectChildren extends elis_database_test {

	protected static function get_overlay_tables() {
		return array(
		    'context' => 'moodle',
		    'course' => 'moodle',
		    'message' => 'moodle',
		    'message_working' => 'moodle',
		    'user' => 'moodle',
		    classmoodlecourse::TABLE => 'elis_program',
		    clusterassignment::TABLE => 'elis_program',
		    clustercurriculum::TABLE => 'elis_program',
		    clustertrack::TABLE => 'elis_program',
		    course::TABLE => 'elis_program',
		    coursecompletion::TABLE => 'elis_program',
		    coursetemplate::TABLE => 'elis_program',
		    curriculum::TABLE => 'elis_program',
		    coursecorequisite::TABLE => 'elis_program',
		    courseprerequisite::TABLE => 'elis_program',
		    curriculumcourse::TABLE => 'elis_program',
		    'crlm_cluster_profile'  => 'elis_program',
		    curriculumstudent::TABLE => 'elis_program',
		    field::TABLE => 'elis_core',
		    instructor::TABLE => 'elis_program',
		    pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
		    student_grade::TABLE => 'elis_program',
		    track::TABLE => 'elis_program',
		    trackassignment::TABLE => 'elis_program',
		    user::TABLE => 'elis_program',
		    usermoodle::TABLE => 'elis_program',
		    userset::TABLE => 'elis_program',
		    usertrack::TABLE => 'elis_program',
		    waitlist::TABLE => 'elis_program'
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
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(usermoodle::TABLE, elis::component_file('program', 'phpunit/usermoodle.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/curriculum.csv'));
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/track.csv'));
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

	/**
	 * Define parameters for the single test method that define the class name and the object properties for that class
	 */
	public static function objectDataProvider() {
	    $timenow = time();

        return array(
            array(
                'classmoodlecourse',
                array(
                    'classid' => 100,
                    'moodlecourseid' => 100,
                    'enroltype' => 1,
                    'enrolplugin' => 'elis',
                    'timemodified' => $timenow,
                    'autocreated' => 1
                )
            ),

            array(
                'clusterassignment',
                array(
                    'clusterid' => 1,
                    'userid' => 100,
                    'plugin' => 'manual',
                    'autoenrol' => 1,
                    'leader' => 1
                )
            ),

            array(
                'clustercurriculum',
                array(
                    'clusterid' => 100,
                    'curriculumid' => 100,
                    'autoenrol' => 1
                )
            ),

            array(
                'clustertrack',
                array(
                    'clusterid' => 100,
                    'trackid' => 100,
                    'autoenrol' => 1,
                    'autounenrol' => 1,
                    'enrolmenttime' => $timenow
                )
            ),

            array(
                'course',
                array(
                    'name' => 'Test Course 101',
                    'code' => 'code',
                    'idnumber' => '__c_c_test101',
                    'syllabus' => 'syllabus',
                    'documents' => 'documents',
                    'lengthdescription' => 'month',
                    'length' => 12,
                    'credits' => '5',
                    'completion_grade' => 90,
                    'environmentid' => 1,
                    'cost' => '99.99',
                    'timecreated' => $timenow,
                    'timemodified' => $timenow,
                    'version' => '0.99a'
                )
            ),

            array(
                'coursecompletion',
                array(
                    'courseid' => 100,
                    'idnumber' => '__c_c_gradeitem101',
                    'name' => 'Moodle Grade Item',
                    'description' => 'Grade item description, woo!',
                    'completion_grade' => 60,
                    'required' => 1
                )
            ),

            array(
                'coursetemplate',
                array(
                    'courseid' => 100,
                    'location' => 'moodle',
                    'templateclass' => 'moodle_course_idnumber'
                ),
            ),

            array(
                'curriculum',
                array(
                    'idnumber' => '__c_c_test101',
                    'name' => 'Test Program 101',
                    'description' => 'Description for Justin\'s program!',
                    'reqcredits' => 10.5,
                    'iscustom' => 1,
                    'timecreated' => $timenow,
                    'timemodified' => $timenow,
                    'timetocomplete' => 'time value',
                    'frequency' => '1 month',
                    'priority' => 99
                )
            ),

            array(
                'coursecorequisite',
                array(
                    'courseid' => 100,
                    'curriculumcourseid' => 50
                )
            ),

            array(
                'courseprerequisite',
                array(
                    'courseid' => 100,
                    'curriculumcourseid' => 50
                )
            ),

            array(
                'curriculumcourse',
                array(
                    'curriculumid' => 100,
                    'courseid' => 110,
                    'required' => 1,
                    'frequency' => 1,
                    'timeperiod' => 'year',
                    'position' => 10,
                    'timecreated' => $timenow,
                    'timemodified' => $timenow
                )
            ),

            array(
                'curriculumstudent',
                array(
                    'userid' => 100,
                    'curriculumid' => 110,
                    'completed' => 1,
                    'timecompleted' => $timenow - 1 * YEARSECS,
                    'timeexpired' => $timenow,
                    'credits' => '5.50',
                    'locked' => 1,
                    'certificatecode' => random_string(10),
                    'timecreated' => $timenow,
                    'timemodified' => $timenow
                )
            ),

            array(
                'instructor',
                array(
                    'classid' => 101,
                    'userid' => 103,
                    'assigntime' => $timenow - 60 * DAYSECS,
                    'completetime' => $timenow
                )
            ),

            array(
                'pmclass',
                array(
                    'idnumber' => '__c_c_test101',
                    'courseid' => 100,
                    'startdate' => $timenow - 60 * DAYSECS,
                    'enddate' => $timenow,
                    'duration' => 1000,
                    'starttimehour' => 9,
                    'starttimeminute' => 15,
                    'endtimehour' => 11,
                    'endtimeminute' => 45,
                    'maxstudents' => 25,
                    'environmentid' => 10,
                    'enrol_from_waitlist' => 1
                )
            ),

            array(
                'student_grade',
                array(
                    'classid' => 100,
                    'userid' => 200,
                    'completionid' => 100,
                    'grade' => '39.00000',
                    'locked' => 1,
                    'timegraded' => $timenow - 1 * HOURSECS,
                    'timemodified' => $timenow
                )
            ),

            array(
                'track',
                array(
                    'curid' => 1,
                    'idnumber' => '__c_t_test101',
                    'name' => 'Test Track 101',
                    'description' => 'Track description',
                    'startdate' => $timenow - 180 * DAYSECS,
                    'enddate' => $timenow + 30 * DAYSECS,
                    'defaulttrack' => 1,
                    'timecreated' => $timenow,
                    'timemodified' => $timenow
                )
            ),

            array(
                'trackassignment',
                array(
                    'trackid' => 1,
                    'classid' => 110,
                    'courseid' => 120,
                    'autoenrol' => 1,
                    'timecreated' => $timenow,
                    'timemodified' => $timenow
                )
            ),

            array(
                'user',
                array(
                    'username' => '__c_u_testuser',
                    'password' => 'password',
                    'idnumber' => '__c_u_userid101',
                    'firstname' => 'Firstname',
                    'lastname' => 'Lastname',
                    'mi' => 'I',
                    'email' => 'testuser@example.com',
                    'email2' => 'testuser2@example.com',
                    'address' => '101 Street St.',
                    'address2' => 'Unit 1',
                    'city' => 'Ottawa',
                    'state' => 'ON',
                    'country' => 'CA',
                    'phone' => '555-123-4567',
                    'phone2' => '800-888-8888',
                    'fax' => 'It\'s 2012, nobody has a fax anymore',
                    'postalcode' => 'O1O 1O1',
                    'birthdate' => '2012-03-22',
                    'gender' => 'O',
                    'language' => 'en', // can't set 'en_utf8' as it is set to 'en' when the object is loaded?!
                    'transfercredits' => 10,
                    'comments' => 'no comment',
                    'notes' => 'Nothing of note.',
                    'timecreated' => $timenow,
                    'timeapproved' => $timenow,
                    'timemodified' => $timenow,
                    'inactive' => 1
                )
            ),

            array(
                'usermoodle',
                array(
                    'cuserid' => 100,
                    'muserid' => 100,
                    'idnumber' => '__c_u_testuser101'
                )
            ),

            array(
                'userset',
                array(
                    'name' => 'Test Userset 101',
                    'display' => 'Fuller-er display name',
                    'parent' => 1,
                    'depth' => 2
                )
            ),

            array(
                'usertrack',
                array(
                    'userid' => 100,
                    'trackid' => 100
                )
            ),

            array(
                'waitlist',
                array(
                    'classid' => 100,
                    'userid' => 103,
                    'timecreated' => $timenow,
                    'timemodified' => $timenow,
                    'position' => 10
                )
            )
	    );
	}


    /**
     *
     *
     * @dataProvider objectDataProvider
     */
    public function testDataClass($classname, $data) {
        global $DB;

        $this->load_csv_data();

        // Some classes will set these values on save, so we need to recalculate them here. =/
        $timenow = time();

        if (isset($data['timecreated'])) {
            $data['timecreated'] = $timenow;
        }
        if (isset($data['timemodified'])) {
            $data['timemodified'] = $timenow;
        }

        // Initialize a new data object and save it to the database
        $data_class = new $classname($data);
        $data_class->save();

        $this->assertGreaterThan(0, $data_class->id, 'class: '.$classname);

        $recordid = $data_class->id;

        // Build an object for comparing the DB return against
        $objclass = (object)$data;
        $objclass->id = $recordid;
        $objclass = (array)$objclass;

        // Verify that the properties are save by reading the DB record directly
        $dbobject = $DB->get_record($classname::TABLE, array('id' => $recordid));
        $dbobject = (array)$dbobject;
        if (isset($dbobject['timecreated'])) {
            $dbobject['timecreated'] = $timenow;
        }
        if (isset($dbobject['timemodified'])) {
            $dbobject['timemodified'] = $timenow;
        }

        //sort the two arrays so we dont get out of order assert fails
        asort($dbobject);
        asort($objclass);
        $this->assertEquals($objclass, $dbobject, 'class: '.$classname);

        // Initialize a new object by record ID
        $new_object = new $classname($recordid);

        // Verify that each property from the data is setup in the new object
        foreach ($data as $key => $val) {
            if ($key === 'timemodified' || $key === 'timecreated') { continue; } //cannot accurately test these values
            $this->assertEquals($val, $new_object->$key, 'class: '.$classname.'->'.$key);
        }
	}
}
