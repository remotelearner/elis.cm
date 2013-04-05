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
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::file('healthpage.class.php'));

class healthpageTest extends elis_database_test {
	protected static function get_overlay_tables() {
        return array(
            course::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
        );
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmusers.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    public function duplicateEnrolmentProvider() {
        return array(
            // Case 1 -- no duplicates, all users enrolled in both classes
            array(
                array(
                    array(
                        'classid' => 100,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 2
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 3
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 2
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 3
                    )
                ),
                0
            ),

            // Case 2 -- one duplicate, one user enrolled multiple times in one class
            array(
                array(
                    array(
                        'classid' => 100,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 1
                    )
                ),
                1
            ),

            // Case 3 -- six duplicates, each user enrolled twice in both courses
            array(
                array(
                    array(
                        'classid' => 100,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 2
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 2
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 3
                    ),
                    array(
                        'classid' => 100,
                        'userid'  => 3
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 1
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 2
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 2
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 3
                    ),
                    array(
                        'classid' => 101,
                        'userid'  => 3
                    )
                ),
                6
            )
        );
    }

    /**
     * Validate that the health page duplicate enrolment check and correctly detect the number of duplicate enrolments
     * in the system.
     *
     * @dataProvider duplicateEnrolmentProvider
     * @param array $enrolments An array of enrolment data to insert into student::TABLE
     * @param int   $count      The number of duplicate enrolments that should be detected
     */
    public function testDuplicateEnrolmentDetection($enrolments, $count) {
        global $DB;

        $this->load_csv_data();

        // Pre-load enrolment data
        foreach ($enrolments as $enrolment) {
            $DB->insert_record(student::TABLE, $enrolment);
        }

        $healthcheck = new health_duplicate_enrolments();
        
        $this->assertEquals($count, $healthcheck->count);
    }
}
