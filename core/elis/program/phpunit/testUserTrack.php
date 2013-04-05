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

/*Data objects*/
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

require_once(elispm::file('phpunit/datagenerator.php'));

class usertrackTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
		return array(
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            curriculumstudent::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            usertrack::TABLE => 'elis_program',
            'context' => 'moodle',
            'user' => 'moodle'
        );
	}

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(usertrack::TABLE, elis::component_file('program', 'phpunit/user_track.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test validation of duplicates
     *
     * @expectedException data_object_validation_exception
     */
    public function testUserTrackValidationPreventsDuplicates() {
        $this->load_csv_data();

        $usertrack = new usertrack(array('userid' => 1,
                                         'trackid' => 1));

        $usertrack->save();
    }

    public function test_enrol() {
        global $DB;

        //fixture
        $elis_gen = new elis_program_datagen_unit($DB);
        $pgm = $elis_gen->create_program();
        $track = $elis_gen->create_track(array('curid' => $pgm->id));
        $course = $elis_gen->create_course();
        $pmclass = $elis_gen->create_pmclass(array('courseid' => $course->id));
        $user = $elis_gen->create_user();

        $elis_gen->assign_class_to_track($pmclass->id, $course->id, $track->id, true);

        $result = usertrack::enrol($user->id,$track->id);
        $this->assertTrue($result);

        //validate curriculumstudent rec
        $rec = $DB->get_record(curriculumstudent::TABLE,array('curriculumid'=>$pgm->id, 'userid'=>$user->id));
        $this->assertNotEmpty($rec);

        //validate student rec
        $rec = $DB->get_record(student::TABLE,array('classid'=>$pmclass->id, 'userid'=>$user->id));
        $this->assertNotEmpty($rec);
    }
}