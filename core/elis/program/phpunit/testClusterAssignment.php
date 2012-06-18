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
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/usertrack.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::lib('data/student.class.php'));

class clusterassignmentTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
		return array(
		    'context' => 'moodle',
		    clusterassignment::TABLE => 'elis_program',
		    userset::TABLE => 'elis_program'
        );
	}

	protected static function get_ignored_tables() {
        return array(usertrack::TABLE => 'elis_program',
                     curriculumstudent::TABLE => 'elis_program',
                     student::TABLE => 'elis_program');
	}

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        $dataset->addTable(clusterassignment::TABLE, elis::component_file('program', 'phpunit/cluster_assignment.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test validation of duplicates
     *
     * @expectedException data_object_validation_exception
     */
    public function testClusterAssignmentValidationPreventsDuplicates() {
        $this->load_csv_data();

        $clusterassignment = new clusterassignment(array('clusterid' => 1,
                                                         'userid' => 1,
                                                         'plugin' => 'manual'));

        $clusterassignment->save();
    }

    /**
     * Test validation of entries with the same userid and clusterid
     * but different plugin
     */
    public function testClusterAssignmentValidationAllowsMultiplePlugins() {
        $this->load_csv_data();

        $clusterassignment = new clusterassignment(array('clusterid' => 1,
                                                         'userid' => 1,
                                                         'plugin' => 'moodle_profile'));

        $clusterassignment->save();

        $this->assertEquals(1, 1);
    }
}