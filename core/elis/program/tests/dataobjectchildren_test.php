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

// Data objects.
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

/**
 * Test data object children.
 * @group elis_program
 */
class dataobjectchildren_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            user::TABLE => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
            userset::TABLE => elis::component_file('program', 'tests/fixtures/userset.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Load smaller set of CSV data
     */
    protected function load_csv_data_timemodified_timecreated() {
        $dataset = $this->createCsvDataSet(array(
                curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Define parameters for the single test method that define the class name and the object properties for that class
     * @return array An array of parameters.
     */
    public static function dataprovider_object() {
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
                        ),
                ),
                array(
                        'clusterassignment',
                        array(
                            'clusterid' => 1,
                            'userid' => 100,
                            'plugin' => 'manual',
                            'autoenrol' => 1,
                            'leader' => 1
                        ),
                ),
                array(
                        'clustercurriculum',
                        array(
                            'clusterid' => 100,
                            'curriculumid' => 100,
                            'autoenrol' => 1
                        ),
                ),
                array(
                        'clustertrack',
                        array(
                            'clusterid' => 100,
                            'trackid' => 100,
                            'autoenrol' => 1,
                            'autounenrol' => 1,
                            'enrolmenttime' => $timenow
                        ),
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
                        ),
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
                        ),
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
                        ),
                ),
                array(
                        'coursecorequisite',
                        array(
                            'courseid' => 100,
                            'curriculumcourseid' => 50
                        ),
                ),
                array(
                        'courseprerequisite',
                        array(
                            'courseid' => 100,
                            'curriculumcourseid' => 50
                        ),
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
                        ),
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
                        ),
                ),
                array(
                        'instructor',
                        array(
                            'classid' => 101,
                            'userid' => 103,
                            'assigntime' => $timenow - 60 * DAYSECS,
                            'completetime' => $timenow
                        ),
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
                        ),
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
                        ),
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
                        ),
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
                        ),
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
                            'language' => 'en', // Can't set 'en_utf8' as it is set to 'en' when the object is loaded.
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
     * Test data classes.
     * @dataProvider dataprovider_object
     */
    public function test_dataclass($classname, $data) {
        global $DB;

        $this->load_csv_data();

        // Some classes will set these values on save, so we need to recalculate them here.
        $timenow = time();

        if (isset($data['timecreated'])) {
            $data['timecreated'] = $timenow;
        }
        if (isset($data['timemodified'])) {
            $data['timemodified'] = $timenow;
        }

        $sink = $this->redirectMessages();

        // Initialize a new data object and save it to the database.
        $dataclass = new $classname($data);
        $dataclass->save();

        $this->assertGreaterThan(0, $dataclass->id, 'class: '.$classname);

        $recordid = $dataclass->id;

        // Build an object for comparing the DB return against.
        $objclass = (object)$data;
        $objclass->id = $recordid;
        $objclass = (array)$objclass;

        // Verify that the properties are save by reading the DB record directly.
        $dbobject = $DB->get_record($classname::TABLE, array('id' => $recordid));
        $dbobject = (array)$dbobject;
        if (isset($dbobject['timecreated'])) {
            $dbobject['timecreated'] = $timenow;
        }
        if (isset($dbobject['timemodified'])) {
            $dbobject['timemodified'] = $timenow;
        }

        // Sort the two arrays so we dont get out of order assert fails.
        asort($dbobject);
        asort($objclass);
        $this->assertEquals($objclass, $dbobject, 'class: '.$classname);

        // Initialize a new object by record ID.
        $newobject = new $classname($recordid);

        // Verify that each property from the data is setup in the new object.
        foreach ($data as $key => $val) {
            // Cannot accurately test these values.
            if ($key === 'timemodified' || $key === 'timecreated') {
                continue;
            }
            $this->assertEquals($val, $newobject->$key, 'class: '.$classname.'->'.$key);
        }
    }

    /**
     * Data provider for ELIS entiries where timemodified and timecreated are not set by default
     * @return array An array of parameters
     */
    public function dataprovider_timecreated_timemodified_object() {
        return array(
                array(
                        'course',
                        array(
                            'name' => 'Test Course 101 2',
                            'code' => 'code',
                            'idnumber' => '__c_c_test101 2',
                            'syllabus' => 'syllabus',
                            'documents' => 'documents',
                            'lengthdescription' => 'month',
                            'length' => 12,
                            'credits' => '5',
                            'completion_grade' => 90,
                            'environmentid' => 1,
                            'cost' => '99.99',
                            'version' => '0.99a'
                        ),
                ),
                array(
                        'curriculum',
                        array(
                            'idnumber' => '__c_c_test101 2',
                            'name' => 'Test Program 101 2',
                            'description' => 'Description for Justin\'s program!',
                            'reqcredits' => 10.5,
                            'iscustom' => 1,
                            'timetocomplete' => 'time value',
                            'frequency' => '1 month',
                            'priority' => 99
                        ),
                ),
                array(
                        'track',
                        array(
                            'curid' => 1,
                            'idnumber' => '__c_t_test101 2',
                            'name' => 'Test Track 101 2',
                            'description' => 'Track description',
                            'startdate' => time() - 180 * DAYSECS,
                            'enddate' => time() + 30 * DAYSECS,
                            'defaulttrack' => 1,
                        ),
                ),
        );
    }

    /**
     * Test data class setting the timecreated and time modified
     * @dataProvider dataprovider_timecreated_timemodified_object
     * @param string $classname the ELIS entity class name
     * @param string $dataset the ELIS entity sample data
     */
    public function test_dataclass_timecreated_timemodified_course($classname, $dataset) {
        global $DB;
        $this->load_csv_data_timemodified_timecreated();
        $this->resetAfterTest(true);

        $entityobject = new $classname($dataset);
        $entityobject->save();

        $dbobject = $DB->get_record($classname::TABLE, array('id' => $entityobject->id));
        // Assert timecreated and timemodified are not equal to zero and that the are equal to each other
        $this->assertNotEquals(0, $dbobject->timecreated);
        $this->assertNotEquals(0, $dbobject->timemodified);
        $this->assertEquals($dbobject->timecreated, $dbobject->timemodified);

        // Force a wait of 1 seconds to simulate some human input dealy
        sleep(1);
        $entityobject = new $classname($dbobject);
        $entityobject->name = 'changed name';
        $entityobject->save();

        $dbobject = $DB->get_record($classname::TABLE, array('id' => $entityobject->id));
        // Assert timecreated and timemodified are not equal to zero and that the are NOT equal to each other
        $this->assertNotEquals(0, $dbobject->timecreated);
        $this->assertNotEquals(0, $dbobject->timemodified);
        $this->assertNotEquals($dbobject->timecreated, $dbobject->timemodified);
    }
}