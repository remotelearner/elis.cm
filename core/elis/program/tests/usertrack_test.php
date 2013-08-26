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

// Libs.
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test usertrack
 * @group elis_program
 */
class usertrack_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            usertrack::TABLE => elis::component_file('program', 'tests/fixtures/user_track.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test validation of duplicates
     *
     * @expectedException data_object_validation_exception
     */
    public function test_usertrackvalidationpreventsduplicates() {
        $this->load_csv_data();
        $usertrack = new usertrack(array('userid' => 1, 'trackid' => 1));
        $usertrack->save();
    }

    /**
     * Test enrol function
     */
    public function test_enrol() {
        global $DB;

        // Fixture.
        $elisgen = new elis_program_datagenerator($DB);
        $pgm = $elisgen->create_program();
        $track = $elisgen->create_track(array('curid' => $pgm->id));
        $course = $elisgen->create_course();
        $pmclass = $elisgen->create_pmclass(array('courseid' => $course->id));
        $user = $elisgen->create_user();

        $elisgen->assign_class_to_track($pmclass->id, $course->id, $track->id, true);

        $result = usertrack::enrol($user->id, $track->id);
        $this->assertTrue($result);

        // Validate curriculumstudent rec.
        $rec = $DB->get_record(curriculumstudent::TABLE, array('curriculumid' => $pgm->id, 'userid' => $user->id));
        $this->assertNotEmpty($rec);

        // Validate student rec.
        $rec = $DB->get_record(student::TABLE, array('classid' => $pmclass->id, 'userid' => $user->id));
        $this->assertNotEmpty($rec);
    }
}