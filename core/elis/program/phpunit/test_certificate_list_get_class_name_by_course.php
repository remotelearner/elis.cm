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
 * @package    elis
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 */

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    define('CLI_SCRIPT', true);
}

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::file('certificatelistpage.class.php'));
require_once(elispm::file('phpunit/datagenerator.php'));

/**
 * PHPUnit test to retrieve a user's certificates
 */
class certificatelist_get_class_name_by_course_test extends elis_database_test  {
    /**
     * @var array Array of globals that will be backed-up/restored for each test.
     */
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Setup overlay tables array
     * @return array Array of overlay tables.
     */
    protected static function get_overlay_tables() {
        return array(
            pmclass::TABLE => 'elis_program',
            course::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
        );
    }

    /**
     * Load PHPUnit CSV data
     */
    protected function load_csv_data() {
        // Load initial data from a CSV file.
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/class.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/class_enrolment.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * This function will setup a fake certificatesettings object for details
     * @return object An stdClass object mimicking the properties of a certificatesettings object
     */
    protected function setup_dummy_certsettings_object() {
        $certobj = new stdClass();
        $certobj->entity_type = '';
        $certobj->entity_id = 0;

        return $certobj;
    }

    /**
     * Test retreiving the name of a class instance using the course id, user id and completetime
     */
    public function test_get_class_name_by_course() {
        $this->load_csv_data();

        $certdata = new stdClass();
        $certdata->cm_userid = 6;
        $certdata->entity_id = 3;
        $certdata->timeissued = 1358139600;

        $certlist = new certificatelistpage();
        $coursename = $certlist->get_class_name_by_course($certdata);

        $this->assertEquals('CLAS202.1', $coursename);
    }

    /**
     * Test retreiving the name of a class instance using an illegit course id
     */
    public function test_get_class_name_by_illegit_course_fail() {
        $this->load_csv_data();

        $certdata = new stdClass();
        $certdata->cm_userid = 6;
        $certdata->entity_id = 99;
        $certdata->timeissued = 1358139600;

        $certlist = new certificatelistpage();
        $coursename = $certlist->get_class_name_by_course($certdata);

        $this->assertEquals(false, $coursename);
    }

    /**
     * Test retreiving the name of a class instance using an illegit argument
     */
    public function test_get_class_name_by_illegit_course_object_fail() {
        $this->load_csv_data();

        $certdata = new stdClass();

        $certlist = new certificatelistpage();
        $coursename = $certlist->get_class_name_by_course($certdata);

        $this->assertEquals(false, $coursename);
    }

    /**
     * Data provider containing incorrect object properties
     * @return array An array of objects
     */
    public function class_name_helper_with_incorrect_cert_setting_property_provider() {
        // Missing cert entity type.
        $certsetting = $this->setup_dummy_certsettings_object();

        $data = array();

        // Missing entity type property.
        unset($certsetting->entity_type);
        $data[] = array(clone($certsetting));

        // Missing entity id property.
        $certsetting->entity_type = 'COURSE';
        unset($certsetting->entity_id);
        $data[] = array(clone($certsetting));

        // Incorrect entity id value.
        $certsetting->entity_type = 'COURSE';
        $certsetting->entity_id = 99;
        $data[] = array(clone($certsetting));

        return $data;
    }

    /**
     * Test helper method for getting the course name via certificate settings data
     * @param object $certsetting A certificatesettings object with missing properties
     * @dataProvider class_name_helper_with_incorrect_cert_setting_property_provider
     */
    public function test_class_name_helper_with_incorrect_cert_setting_data($certsetting) {
        $certlist = new certificatelistpage();
        $result = $certlist->get_cert_entity_name($certsetting);

        $this->assertEquals(false, $result);
    }

    /**
     * Test helper method for getting the course name via certificate settings data
     */
    public function test_class_name_helper_with_cert_setting_data() {
        $this->load_csv_data();

        $certdata = new stdClass();
        $certdata->id = 1;
        $certdata->csid = 2;
        $certdata->cm_userid = 6;
        $certdata->entity_id = 3;
        $certdata->timeissued = 1358139600;
        $certdata->entity_type = CERTIFICATE_ENTITY_TYPE_COURSE;

        $certlist = new certificatelistpage();
        $certlist->get_cert_entity_name($certdata);

        $result = $certlist->entitynamebuffer[CERTIFICATE_ENTITY_TYPE_COURSE][0];

        // The output string may change the format in which the certificate are displayed.
        // As long as the string contains both bits of information (query string and course name).
        $this->assertRegExp('/entity_certificate.php\?id=1\&amp;csid=2/', $result);
        $this->assertRegExp('/Test Course 0/', $result);
    }
}