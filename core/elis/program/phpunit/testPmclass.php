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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

//data objects
require_once(elispm::lib('data/curriculumcourse.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/user.class.php'));

class accesslibTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            curriculumcourse::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
        );
    }

    public function test_get_completion_counts() {
        //fixture
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(student::TABLE, elis::component_file('program', 'phpunit/student.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //test
        $pmclass = new pmclass;
        $pmclass->id = 100;
        $completion_counts = $pmclass->get_completion_counts();

        //verify results
        $this->assertInternalType('array',$completion_counts);
        $this->assertEquals(3,sizeof($completion_counts));
        $this->assertArrayHasKey(STUSTATUS_NOTCOMPLETE,$completion_counts);
        $this->assertArrayHasKey(STUSTATUS_FAILED,$completion_counts);
        $this->assertArrayHasKey(STUSTATUS_PASSED,$completion_counts);
        $this->assertEquals(1,$completion_counts[STUSTATUS_NOTCOMPLETE]);
        $this->assertEquals(0,$completion_counts[STUSTATUS_FAILED]);
        $this->assertEquals(0,$completion_counts[STUSTATUS_PASSED]);
    }

    public function test_format_course_listing() {
        //fixture
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //test
        $courses = array(1=>null, 100=>null);
        $pmclass = new pmclass;
        $listing = $pmclass->format_course_listing($courses);

        //verify
        $expected = array(
            1 => array(
                1 => 1,
                100 => 2
            )
        );

        $this->assertEquals($expected,$listing);
    }

    /**
     * Data provider for test method test_pmclass_auto_create_class()
     * for ELIS-6854
     * @return array  parameters for test method
     *                format: array(
     *                    array(pmclassdata),
     *                    array(autocreateparams),
     *                    expected auto_create_class() return: false => false, true => !false
     *                    newclass startdate (if applicable)
     *                    newclass enddate (if applicable)
     *                )
     */
    public function pmclass_auto_create_dataprovider() {
        return array(
            array( // no courseid => acc returns false
                array(
                    'idnumber' => 'ci-elis-6854a',
                    'maxstudents' => 12
                ),
                array(
                ),
                false, // accreturn
                null,
                null
            ),
            array( // no courseid => acc returns false
                array(
                    'idnumber' => 'ci-elis-6854b',
                    'maxstudents' => 12,
                    'startdate' => 1234567,
                    'enddate' => 2345678,
                ),
                array(
                ),
                false, // accreturn
                null,
                null
            ),
            array( // no courseid => acc returns false
                array(
                    'idnumber' => 'ci-elis-6854c',
                    'maxstudents' => 12
                ),
                array(
                    'startdate' => 1234567,
                    'enddate' => 2345678,
                ),
                false, // accreturn
                null,
                null
            ),
            array( // courseid => acc returns id
                array(
                    'idnumber' => 'ci-elis-6854d',
                    'courseid' => 2,
                    'maxstudents' => 12
                ),
                array(
                ),
                true,
                0,
                0
            ),
            array( // courseid in extra params => acc returns id
                array(
                    'idnumber' => 'ci-elis-6854e',
                    'maxstudents' => 12
                ),
                array(
                    'courseid' => 2
                ),
                true,
                0,
                0
            ),
            array( // courseid => acc returns id
                array(
                    'idnumber' => 'ci-elis-6854f',
                    'courseid' => 2,
                    'maxstudents' => 12,
                    'startdate' => 1234567,
                    'enddate' => 2345678,
                ),
                array(
                ),
                true,
                1234567,
                2345678
            ),
            array( // courseid => acc returns id
                array(
                    'idnumber' => 'ci-elis-6854g',
                    'courseid' => 2,
                    'maxstudents' => 12
                ),
                array(
                    'startdate' => 1234567,
                    'enddate' => 2345678,
                ),
                true,
                1234567,
                2345678
            ),
        );
    }

    /**
     * Method to test pmclass:auto_create_class()
     * for ELIS-6854
     * @dataProvider pmclass_auto_create_dataprovider
     * @param array  $pmclassdata       data to pass to pmclass contructor
     * @param array  $autocreateparams  data to pass to pmclass::auto_create_class
     * @param bool   $accreturn         false if pmclass::auto_create_class should return false, true otherwise
     * @param int    $startdate         expected new class startdate
     * @param int    $enddate           expected new class enddate
     */
    public function test_pmclass_auto_create_class($pmclassdata, $autocreateparams, $accreturn, $startdate, $enddate) {
        $pmclass = new pmclass((object)$pmclassdata);
        $realaccret = $pmclass->auto_create_class($autocreateparams);
        if ($accreturn) {
            $this->assertTrue(!empty($realaccret));
            $this->assertEquals($startdate, $pmclass->startdate);
            $this->assertEquals($enddate, $pmclass->enddate);
        } else {
            $this->assertFalse($realaccret);
        }
    }
}
