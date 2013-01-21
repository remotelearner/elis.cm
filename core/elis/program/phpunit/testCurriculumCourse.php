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
require_once(elispm::lib('data/curriculumcourse.class.php'));

class curriculumcourseTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
		return array(
            curriculumcourse::TABLE => 'elis_program',
            courseprerequisite::TABLE => 'elis_program',
            coursecorequisite::TABLE => 'elis_program',
        );
	}

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test validation of duplicates
     *
     * @expectedException data_object_validation_exception
     */
    public function testCurriculumCourseValidationPreventsDuplicates() {
        $this->load_csv_data();

        $curriculumcourse = new curriculumcourse(array('curriculumid' => 1,
                                                       'courseid' => 1));

        $curriculumcourse->save();
    }

    public function test_get_prerequisites() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        $dataset->addTable(courseprerequisite::TABLE, elis::component_file('program', 'phpunit/pmcourse_prerequisite.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $curriculumcourse = new curriculumcourse;
        $curriculumcourse->id = 2;
        $prereqs = $curriculumcourse->get_prerequisites();

        $this->assertEquals(array(100),$prereqs);
    }

    public function test_get_corequisites() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculumcourse::TABLE, elis::component_file('program', 'phpunit/curriculum_course.csv'));
        $dataset->addTable(coursecorequisite::TABLE, elis::component_file('program', 'phpunit/pmcourse_corequisite.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $curriculumcourse = new curriculumcourse;
        $curriculumcourse->id = 2;
        $coreqs = $curriculumcourse->get_corequisites();

        $this->assertEquals(array(100),$coreqs);
    }
}