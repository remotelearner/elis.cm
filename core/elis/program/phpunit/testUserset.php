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
require_once($CFG->dirroot . '/elis/program/accesslib.php');
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));
require_once(elispm::file('usersetpage.class.php'));

class usersetTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'cache_flags' => 'moodle',
            'context' => 'moodle',
            'course' => 'moodle',
            'elis_files_userset_store' => 'repository_elis_files',
            'grading_areas' => 'moodle',
            'role' => 'moodle',
            'role_assignments' => 'moodle',
            'role_capabilities' => 'moodle',
            'role_context_levels' => 'moodle',
            'role_names' => 'moodle',
            'user' => 'moodle',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            userset::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            userset_profile::TABLE => 'elis_program',
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
            'elis_field' => 'elis_core',
            field_data_int::TABLE => 'elis_core',
            field_data_num::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_text::TABLE => 'elis_core',
            'block_instances' => 'moodle',
            'block_positions' => 'moodle',
            'filter_active' => 'moodle',
            'filter_config' => 'moodle',
            'comments' => 'moodle',
            'rating' => 'moodle'
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
        $syscontext = self::$origdb->get_record('context', array('contextlevel' => CONTEXT_COURSE,
                                                                  'instanceid' => SITEID));
        self::$overlaydb->import_record('context', $syscontext);
    }

    protected function load_csv_data() {
        // load initial data from a CSV file
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        $dataset->addTable('user', elis::component_file('program', 'phpunit/mdluser.csv'));
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

        accesslib_clear_all_caches(true);
        // make sure all the contexts are created, so that we can find the children
        $contextclass = context_elis_helper::get_class_for_level(CONTEXT_ELIS_USERSET);
        for ($i = 1; $i <= 4; $i++) {
            $cluster_context_instance     = $contextclass::instance($i);
        }

      /*  global $DB;
        echo "\ncontext::TABLE => ";
        var_dump($DB->get_records('context'));
        echo "\nuserset::TABLE (pre-delete)=> ";
        var_dump($DB->get_records(userset::TABLE));
      */

        // delete a record
        $src = new userset(2, null, array(), false, array(), self::$overlaydb);
        $src->deletesubs = false;
        $src->delete();

      /*
        echo "\nuserset::TABLE (post_delete)=> ";
        var_dump($DB->get_records(userset::TABLE));
      */

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

        accesslib_clear_all_caches(true);
        // make sure all the contexts are created, so that we can find the children
        for ($i = 1; $i <= 4; $i++) {
            $cluster_context_instance = context_elis_userset::instance($i);
        }

        // delete a record
        $src = new userset(2, null, array(), false, array(), self::$overlaydb);
        $src->deletesubs = true;
        $src->delete();

        // read it back
        $recordset = self::$overlaydb->get_recordset(userset::TABLE, null, '', 'name,display,parent,depth,id');
        $result = new moodle_recordset_phpunit_datatable(userset::TABLE, $recordset);
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset_delete_subset_b_test_result.csv'));
        $this->assertTablesEqual($dataset->getTable(userset::TABLE), $result);
    }

    /**
     * Verify that getting the cluster listing works.
     */
    public function testClusterGetListing() {
        $this->load_csv_data();

        $clusters = cluster_get_listing('priority, name', 'ASC', 0, 5, '', '', array('parent' => 0));

        $this->assertNotEmpty($clusters);
    }

    /**
     * Test whether a user can enrol users into a sub-userset if they have the required capability on the
     * parent userset.
     */
    public function testCanEnrolIntoClusterWithParentPermission() {
        global $DB;

        $this->load_csv_data();

        // create role with cap: 'elis/program:class_view'
        $testrole = new stdClass;
        $testrole->name = 'ELIS Sub-Userset Manager';
        $testrole->shortname = '_test_ELIS_3848';
        $testrole->description = 'ELIS userset enrol into sub-userser';
        $testrole->archetype = '';
        $testrole->id = create_role($testrole->name, $testrole->shortname, $testrole->description, $testrole->archetype);

        // Ensure our new role is assignable to ELIS class contexts
        set_role_contextlevels($testrole->id, array(CONTEXT_ELIS_USERSET));

        // Ensure the role has our required capability assigned
        $syscontext = context_system::instance();
        assign_capability('elis/program:userset', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        assign_capability('elis/program:userset_view', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        assign_capability('elis/program:userset_create', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        assign_capability('elis/program:userset_enrol_userset_user', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        $syscontext->mark_dirty();

        // Assign a test user a role within the parent userset
        $context = context_elis_userset::instance(1);
        role_assign($testrole->id, 100, $context->id);
        $context->mark_dirty();

        // switch to testuser
        $USER = $DB->get_record('user', array('id' => 100));
        $USER->access = get_user_accessdata($USER->id);
        load_role_access_by_context($testrole->id, $context, $USER->access); // We need to force the accesslib cache to refresh
        $GLOBALS['USER'] = $USER;

        // Check if the user can enrol users into the sub-userset
        $this->assertTrue(usersetpage::can_enrol_into_cluster(2));
    }

    /**
     * Test whether a user can enrol users into a sub-userset if they have the required capability on the
     * parent userset.
     */
    public function testGetAllowedClustersWithParentPermission() {
        global $DB;

        $this->load_csv_data();

        // create role with cap: 'elis/program:class_view'
        $testrole = new stdClass;
        $testrole->name = 'ELIS Sub-Userset Manager';
        $testrole->shortname = '_test_ELIS_3848';
        $testrole->description = 'ELIS userset enrol into sub-userser';
        $testrole->archetype = '';
        $testrole->id = create_role($testrole->name, $testrole->shortname, $testrole->description, $testrole->archetype);

        // Ensure our new role is assignable to ELIS class contexts
        set_role_contextlevels($testrole->id, array(CONTEXT_ELIS_USERSET));

        // Ensure the role has our required capability assigned
        $syscontext = context_system::instance();
        assign_capability('elis/program:userset', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        assign_capability('elis/program:userset_view', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        assign_capability('elis/program:userset_create', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        assign_capability('elis/program:userset_enrol_userset_user', CAP_ALLOW, $testrole->id, $syscontext->id, true);
        $syscontext->mark_dirty();

        // Assign a test user a role within the parent userset
        $context = context_elis_userset::instance(1);
        role_assign($testrole->id, 100, $context->id);

        // Assign a test user a role within the sub-sub-userset
        $ctx2 = context_elis_userset::instance(4);
        role_assign($testrole->id, 100, $ctx2->id);

        // switch to testuser
        $USER = $DB->get_record('user', array('id' => 100));
        $USER->access = get_user_accessdata($USER->id);
        load_role_access_by_context($testrole->id, $context, $USER->access); // We need to force the accesslib cache to refresh
        $GLOBALS['USER'] = $USER;

        // Check which of the parent usersets the user has access to based on the sub-userset
        $allowed = userset::get_allowed_clusters(2);
        $this->assertInternalType('array', $allowed);
        $this->assertEquals(1, count($allowed));

        // Check which of the parent usersets the user has access to basdd on the sub-sub-userset
        $allowed = userset::get_allowed_clusters(4);
        $this->assertInternalType('array', $allowed);
        $this->assertEquals(2, count($allowed));
    }

    public function testDeleteParentPromoteChildren() {
        //load great-grandfather, grandfather, parent, child usersets. ids 5,6,7,8, respectively
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset_grandfathers.csv'));
        $dataset->addTable('context', elis::component_file('program', 'phpunit/userset_context.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //delete grandfather userset
        $grandfather = new userset(6);
        $grandfather->load();
        $grandfather->deletesubs = 0;
        $grandfather->delete();

        $parent = new userset(7);
        $parent->load();

        $child = new userset(8);
        $child->load();

        $this->assertEquals('0',$parent->parent);
        $this->assertEquals('1',$parent->depth);

        $this->assertEquals('7',$child->parent);
        $this->assertEquals('2',$child->depth);
    }
}
