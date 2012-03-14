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
require_once(elispm::lib('data/userset.class.php'));

class usersetTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'context'      => 'moodle',
            'course'       => 'moodle',
            userset::TABLE => 'elis_program',
        );
    }

    protected static function get_ignored_tables() {
        return array(
            // these aren't actually used, but userset::delete will run a query
            // on them
            clustercurriculum::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program',
            clusterassignment::TABLE => 'elis_program',
            userset_profile::TABLE => 'elis_program',
            field_data_int::TABLE => 'elis_core',
            field_data_num::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            'block_instances' => 'moodle',
            'block_positions' => 'moodle',
            'filter_active' => 'moodle',
            'filter_config' => 'moodle',
            'comments' => 'moodle',
            'rating' => 'moodle',
            'cache_flags' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'role_names' => 'moodle',
        );
    }

    protected function setUp() {
        parent::setUp();
        $this->setUpContextsTable();
    }

    /**
     * Set up the contexts table with the minimum that we need.
     */
    private function setUpContextsTable() {
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_SYSTEM));
        self::$overlaydb->import_record('context', $syscontext);

        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);
        $sitecontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $sitecontext);
    }

    protected function load_csv_data() {
        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Test that data class has correct DB fields
     */
    public function testDataClassHasCorrectDBFields() {
        $testobj = new userset(false, null, array(), false, array(), self::$origdb);
        $this->assertTrue($testobj->_test_dbfields(), 'Error(s) with class $_dbfield_ properties.');
    }

    /**
     * Test that data class has correct associations
     */
    public function testDataClassHasCorrectAssociations() {
        $testobj = new userset(false, null, array(), false, array(), self::$origdb);
        $this->assertTrue($testobj->_test_associations(), 'Error(s) with class associations.');
    }

    /**
     * Test that a record can be created in the database.
     */
    public function testCanCreateRecord() {

        // create a record
        $src = new userset(false, null, array(), false, array(), self::$overlaydb);
        $src->name = 'User set';
        $src->display = 'Some description';
        $src->save();

        // read it back
        $retr = new userset($src->id, null, array(), false, array(), self::$overlaydb);
        $this->assertEquals($src->name, $retr->name);
        $this->assertEquals($src->display, $retr->display);
    }

    /**
     * Test that a record can be modified.
     */
    public function testCanUpdateRecord() {
        $this->load_csv_data();

        // read a record
        $src = new userset(3, null, array(), false, array(), self::$overlaydb);
        // modify the data
        $src->name = 'Sub-sub set 2';
        $src->display = 'Sub sub user set';
        $src->parent = 2;
        $src->save();

        // read it back
        $result = new moodle_recordset_phpunit_datatable(userset::TABLE, userset::find(null, array(), 0, 0, self::$overlaydb));
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset_update_test_result.csv'));
        $this->assertTablesEqual($dataset->getTable(userset::TABLE), $result);
    }

    /**
     * Test that you can delete and promote user subsets
     */
    public function testDeletingRecordCanPromoteUserSubsets() {
        $this->load_csv_data();

        // make sure all the contexts are created, so that we can find the children
        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'elis_program');
        for ($i = 1; $i <= 4; $i++) {
            $cluster_context_instance = get_context_instance($cluster_context_level, $i);
        }

        // delete a record
        $src = new userset(2, null, array(), false, array(), self::$overlaydb);
        $src->deletesubs = false;
        $src->delete();

        // read it back
        $result = new moodle_recordset_phpunit_datatable(userset::TABLE, userset::find(null, array(), 0, 0, self::$overlaydb));
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset_promote_test_result.csv'));
        $this->assertTablesEqual($dataset->getTable(userset::TABLE), $result);
    }

    /**
     * Test that you can delete a user set and all its user subsets
     */
    public function testDeleteRecordCanDeleteUserSubsets() {
        $this->load_csv_data();

        // make sure all the contexts are created, so that we can find the children
        $cluster_context_level = context_level_base::get_custom_context_level('cluster', 'elis_program');
        for ($i = 1; $i <= 4; $i++) {
            $cluster_context_instance = get_context_instance($cluster_context_level, $i);
        }

        // delete a record
        $src = new userset(2, null, array(), false, array(), self::$overlaydb);
        $src->deletesubs = true;
        $src->delete();

        // read it back
        $result = new moodle_recordset_phpunit_datatable(userset::TABLE, userset::find(null, array(), 0, 0, self::$overlaydb));
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset_delete_subset_test_result.csv'));
        $this->assertTablesEqual($dataset->getTable(userset::TABLE), $result);
    }
}
