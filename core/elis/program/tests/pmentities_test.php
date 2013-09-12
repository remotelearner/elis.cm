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
require_once(elispm::file('curriculumpage.class.php'));
require_once(elispm::file('trackpage.class.php'));
require_once(elispm::file('coursepage.class.php'));
require_once(elispm::file('pmclasspage.class.php'));
require_once(elispm::file('userpage.class.php'));
require_once(elispm::file('usersetpage.class.php'));

/**
 * Tests for validating that creating a new entity from the UI works
 * @group elis_program
 */
class pmentities_testcase extends elis_database_test {

    /**
     * Set up initial data and set user to testing user.
     */
    protected function setUp() {
        parent::setUp();
        $this->load_csv_data();
        $this->setUser(100);
    }

    /**
     * Reset user.
     */
    protected function tearDown() {
        $this->setUser(null);
        parent::tearDown();
    }

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user' => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            'crlm_course' => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            'crlm_curriculum' => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Creates the two roles required to test default role association. The first role contains the 'view' and
     * 'create' permissions for the entity and the second contains the 'view' and 'edit' capabilities for the entity.
     *
     * @param string $entity The name of the entity being created.
     * @return array An array containg the role for the creator / editor role
     */
    private function create_roles($entity) {
        $syscontext = get_context_instance(CONTEXT_SYSTEM);

        $role1 = create_role($entity.'_creator', $entity.'_creator', $entity.'_creator');
        assign_capability('elis/program'.$entity.'_view', CAP_ALLOW, $role1, $syscontext->id);
        assign_capability('elis/program'.$entity.'_create', CAP_ALLOW, $role1, $syscontext->id);

        $role2 = create_role($entity.'_editor', $entity.'_editor', $entity.'_editor');
        assign_capability('elis/program'.$entity.'_view', CAP_ALLOW, $role2, $syscontext->id);
        assign_capability('elis/program'.$entity.'_create', CAP_ALLOW, $role2, $syscontext->id);

        return array($role1, $role2);
    }

    /**
     * Test creating a new program entity with a default role assignment defined.
     */
    public function test_createprogramwithdefaultroleassignment() {
        global $DB, $USER;

        list($rcid, $reid) = $this->create_roles('program');

        // Setup the editor role to be the default role for the program context.
        elis::$config->elis_program->default_curriculum_role_id = $reid;

        $sysctx = get_context_instance(CONTEXT_SYSTEM);

        // Assign the test user the creator role.
        role_assign($rcid, 100, $sysctx->id);

        // Create a new program entity.
        $data = array(
            'idnumber' => 'program100',
            'name' => 'program100',
            'description' => 'program100'
        );

        $obj = new curriculum($data);
        $obj->save();

        // Initialize a new program management page and invoke the code that handles default role assignments.
        $page = new curriculumpage();
        $page->after_cm_entity_add($obj);

        $programctx = context_elis_program::instance($obj->id);
        $params = array('roleid' => $reid, 'userid' => $USER->id, 'contextid' => $programctx->id);
        $this->assertTrue($DB->record_exists('role_assignments', $params));
    }

    /**
     * Test creating a new track entity with a default role assignment defined.
     */
    public function test_createtrackwithdefaultroleassignment() {
        global $DB, $USER;

        list($rcid, $reid) = $this->create_roles('track');

        // Setup the editor role to be the default role for the track context.
        elis::$config->elis_program->default_track_role_id = $reid;

        $sysctx = get_context_instance(CONTEXT_SYSTEM);

        // Assign the test user the creator role.
        role_assign($rcid, $USER->id, $sysctx->id);

        // Create a new track entity.
        $data = array(
            'curid' => '1',
            'idnumber' => 'track100',
            'name' => 'track100',
            'description' => 'track100'
        );

        $obj = new track($data);
        $obj->save();

        // Initialize a new track management page and invoke the code that handles default role assignments.
        $page = new trackpage();
        $page->after_cm_entity_add($obj);

        $trackctx = context_elis_track::instance($obj->id);
        $params = array('roleid' => $reid, 'userid' => $USER->id, 'contextid' => $trackctx->id);
        $this->assertTrue($DB->record_exists('role_assignments', $params));
    }

    /**
     * Test creating a new course entity with a default role assignment defined.
     */
    public function test_createcoursewithdefaultroleassignment() {
        global $DB;

        list($rcid, $reid) = $this->create_roles('course');

        // Setup the global USER object to be our test user.
        $USER = $DB->get_record('user', array('id' => 100));

        // Setup the editor role to be the default role for the course context.
        elis::$config->elis_program->default_course_role_id = $reid;

        $sysctx = get_context_instance(CONTEXT_SYSTEM);

        // Assign the test user the creator role.
        role_assign($rcid, $USER->id, $sysctx->id);

        // Create a new course entity.
        $data = array(
            'idnumber' => 'course100',
            'name'     => 'course100',
            'syllabus' => 'course100'
        );

        $obj = new course($data);
        $obj->save();

        // Initialize a new course management page and invoke the code that handles default role assignments.
        $page = new coursepage();
        $page->after_cm_entity_add($obj);

        $coursectx = context_elis_course::instance($obj->id);
        $params = array('roleid' => $reid, 'userid' => $USER->id, 'contextid' => $coursectx->id);
        $this->assertTrue($DB->record_exists('role_assignments', $params));
    }

    /**
     * Test creating a new class entity with a default role assignment defined.
     */
    public function test_createclasswithdefaultroleassignment() {
        global $USER, $DB;

        list($rcid, $reid) = $this->create_roles('class');

        // Setup the editor role to be the default role for the class context.
        elis::$config->elis_program->default_class_role_id = $reid;

        $sysctx = get_context_instance(CONTEXT_SYSTEM);

        // Assign the test user the creator role.
        role_assign($rcid, $USER->id, $sysctx->id);

        // Create a new class entity.
        $data = array(
            'courseid'    => 100,
            'idnumber'    => 'program100',
            'name'        => 'program100',
            'description' => 'program100'
        );

        $obj = new pmclass($data);
        $obj->save();

        // Initialize a new class management page and invoke the code that handles default role assignments.
        $page = new pmclasspage();

        $sink = $this->redirectMessages();
        $page->after_cm_entity_add($obj);

        $classctx = context_elis_class::instance($obj->id);
        $params = array('roleid' => $reid, 'userid' => $USER->id, 'contextid' => $classctx->id);
        $this->assertTrue($DB->record_exists('role_assignments', $params));
    }

    /**
     * Test creating a new userset entity with a default role assignment defined.
     */
    public function test_createusersetwithdefaultroleassignment() {
        global $USER, $DB;

        list($rcid, $reid) = $this->create_roles('userset');

        // Setup the editor role to be the default role for the userset context.
        elis::$config->elis_program->default_cluster_role_id = $reid;

        $sysctx = get_context_instance(CONTEXT_SYSTEM);

        // Assign the test user the creator role.
        role_assign($rcid, $USER->id, $sysctx->id);

        // Create a new userset entity.
        $data = array(
            'idnumber'    => 'program100',
            'name'        => 'program100',
            'description' => 'program100'
        );

        $obj = new userset($data);
        $obj->save();

        // Initialize a new userset management page and invoke the code that handles default role assignments.
        $page = new usersetpage();
        $page->after_cm_entity_add($obj);

        $usersetctx = context_elis_userset::instance($obj->id);
        $params = array('roleid' => $reid, 'userid' => $USER->id, 'contextid' => $usersetctx->id);
        $this->assertTrue($DB->record_exists('role_assignments', $params));
    }
}