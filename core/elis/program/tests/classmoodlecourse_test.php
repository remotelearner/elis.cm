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
require_once(elispm::lib('data/classmoodlecourse.class.php'));

/**
 * Test the classmoodlecourse data object.
 * @group elis_program
 */
class classmoodlecourse_testcase extends elis_database_test {
    /**
     * Test validation of duplicates
     * @expectedException data_object_validation_exception
     */
    public function test_classmoodlecourse_preventsduplicates() {
        $dataset = $this->createCsvDataSet(array(
            classmoodlecourse::TABLE => elis::component_file('program', 'tests/fixtures/class_moodle_course.csv')
        ));
        $this->loadDataSet($dataset);

        $classmoodlecourse = new classmoodlecourse(array('classid' => 100, 'moodlecourseid' => 100));
        $classmoodlecourse->save();
    }
}