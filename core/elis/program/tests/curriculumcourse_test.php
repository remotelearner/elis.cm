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
require_once(elispm::lib('data/curriculumcourse.class.php'));

/**
 * Test curriculumcourse dataobject.
 * @group elis_program
 */
class curriculumcourse_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test validation of duplicates
     * @expectedException data_object_validation_exception
     */
    public function test_curriculumcourse_validationpreventsduplicates() {
        $this->load_csv_data();
        $curriculumcourse = new curriculumcourse(array('curriculumid' => 1, 'courseid' => 1));
        $curriculumcourse->save();
    }

    /**
     * Test get_prerequisites function.
     */
    public function test_get_prerequisites() {
        $dataset = $this->createCsvDataSet(array(
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv'),
            courseprerequisite::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse_prerequisite.csv'),
        ));
        $this->loadDataSet($dataset);

        $curriculumcourse = new curriculumcourse;
        $curriculumcourse->id = 2;
        $prereqs = $curriculumcourse->get_prerequisites();

        $this->assertEquals(array(100), $prereqs);
    }

    /**
     * Test get_corequisites function.
     */
    public function test_get_corequisites() {
        $dataset = $this->createCsvDataSet(array(
            curriculumcourse::TABLE => elis::component_file('program', 'tests/fixtures/curriculum_course.csv'),
            coursecorequisite::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse_corequisite.csv'),
        ));
        $this->loadDataSet($dataset);

        $curriculumcourse = new curriculumcourse;
        $curriculumcourse->id = 2;
        $coreqs = $curriculumcourse->get_corequisites();

        $this->assertEquals(array(100), $coreqs);
    }
}