<?php
/**
 * Test creation of PM elements.
 *
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
 * @subpackage program
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
require_once(elis::lib('data/customfield.class.php'));
require_once(elispm::lib('data/clusterassignment.class.php'));
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));

class test_element_creation extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'context'                  => 'moodle',
            'course'                   => 'moodle',
            'user'                     => 'moodle',
            'grading_areas'            => 'moodle',
            'elis_files_userset_store' => 'repository_elis_files',
            curriculum::TABLE          => 'elis_program',
            track::TABLE               => 'elis_program',
            course::TABLE              => 'elis_program',
            coursetemplate::TABLE      => 'elis_program',
            field::TABLE               => 'elis_core',
            field_data_int::TABLE      => 'elis_core',
            field_data_num::TABLE      => 'elis_core',
            field_data_char::TABLE     => 'elis_core',
            field_data_text::TABLE     => 'elis_core',
            pmclass::TABLE             => 'elis_program',
            user::TABLE                => 'elis_program',
            usermoodle::TABLE          => 'elis_program',
            userset_profile::TABLE     => 'elis_program',
            userset::TABLE             => 'elis_program'
        );
    }

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array(
            'block_instances'   => 'moodle',
            'block_positions'   => 'moodle',
            'cache_flags'       => 'moodle',
            'comments'          => 'moodle',
            'filter_active'     => 'moodle',
            'filter_config'     => 'moodle',
            'rating'            => 'moodle',
            'role_assignments'  => 'moodle',
            'role_capabilities' => 'moodle',
            'role_names'        => 'moodle',
            clusterassignment::TABLE => 'elis_program',
            clustercurriculum::TABLE => 'elis_program',
            clustertrack::TABLE => 'elis_program'
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

    /**
     * Initialize a new program object
     *
     * @param none
     * @return curriculum The new program object
     */
    private function initProgram() {
        $data = array(
            'idnumber' => 'TESTID001',
            'name'     => 'Test Program 1'
        );

        $newprogram = new curriculum($data);

        return $newprogram;
    }

    /**
     * Initialize a new track object
     *
     * @param integer $curid A curriculum record ID
     * @return track The new track object
     */
    private function initTrack($curid) {
        $data = array(
            'curid'    => $curid,
            'idnumber' => 'TESTID001',
            'name'     => 'Test Track 1'
        );

        $newtrack = new track($data);

        return $newtrack;
    }

    /**
     * Initialize a new course description object
     *
     * @return course The new course object
     */
    private function initCourse() {
        $data = array(
            'idnumber' => 'TESTID001',
            'name'     => 'Test Course 1',
            'syllabus' => ''  // For some reason this field needs to be defined, or INSERT fails?!
        );

        $newcourse = new course($data);

        return $newcourse;
    }

    /**
     * Initialize a new class object
     *
     * @param integer $courseid A course record ID
     * @return class The new class object
     */
    private function initClass($courseid) {
        $data = array(
            'idnumber' => 'TESTID001',
            'courseid' => $courseid
        );

        $newclass = new pmclass($data);

        return $newclass;
    }

    /**
     * Initialize a new user description object
     *
     * @return user The new user object
     */
    private function initUser() {
        $data = array(
            'idnumber'  => 'TESTID001',
            'username'  => 'testuser1',
            'firstname' => 'Test',
            'lastname'  => 'User1',
            'email'     => 'testuser1@example.com',
            'country'   => 'us'
        );

        $newuser = new user($data);

        return $newuser;
    }

    /**
     * Initialize a new user description object
     *
     * @return userset The new userset object
     */
    private function initUserset() {
        $data = array(
            'name'    => 'Test User Set 1',
            'display' => 'We\'re just testing user set creation!'
        );

        $newuserset = new userset($data);

        return $newuserset;
    }

    /**
     * Test that a new Program context instance can be created and saved to the database.
     */
    public function testProgramContext() {
        $newobj = $this->initProgram();
        $newobj->save();

        $context = context_elis_program::instance($newobj->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_PROGRAM, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new Track context instance can be created and saved to the database.
     */
    public function testTrackContext() {
        $program = $this->initProgram();
        $program->save();

        $newobj = $this->initTrack($program->id);
        $newobj->save();

        $context = context_elis_track::instance($newobj->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_TRACK, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $pctx = context_elis_program::instance($program->id);
        $path = '/'.SYSCONTEXTID.'/'.$pctx->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new Course context instance can be created and saved to the database.
     */
    public function testCourseContext() {
        $newobj = $this->initCourse();
        $newobj->save();

        $context = context_elis_course::instance($newobj->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_COURSE, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new Class context instance can be created and saved to the database.
     */
    public function testClassContext() {
        $course = $this->initCourse();
        $course->save();

        $newobj = $this->initClass($course->id);
        $newobj->save();

        $context = context_elis_class::instance($newobj->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_CLASS, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $cctx = context_elis_course::instance($course->id);
        $path = '/'.SYSCONTEXTID.'/'.$cctx->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new User context instance can be created and saved to the database.
     */
    public function testUserContext() {
        $newobj = $this->initUser();
        $newobj->save();

        $context = context_elis_user::instance($newobj->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USER, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new parent User Set context instance can be created and saved to the database.
     */
    public function testParentUsersetContext() {
        $newobj = $this->initUserset();
        $newobj->save();

        $context = context_elis_userset::instance($newobj->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new parent User Set context instance can be created and saved to the database.
     */
    public function testChildUsersetContext() {
        $newobj = $this->initUserset();
        $newobj->save();

        $ctx1 = context_elis_userset::instance($newobj->id);

        $data = array(
            'name'    => 'Test Sub User Set 1A',
            'display' => 'We\'re just testing user set creation with child user sets!',
            'parent'  => $newobj->id
        );

        $subuserset = new userset($data);
        $subuserset->save();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that a context record was actually created with correct values
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$ctx1->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a child User Set context instance is updated when that User Set
     * is promoted to the top level
     */
    public function testChildUsersetContextOnPromotion() {
        $newobj = $this->initUserset();
        $newobj->save();

        $ctx1 = context_elis_userset::instance($newobj->id);

        $data = array(
            'name'    => 'Test Sub User Set 1A',
            'display' => 'We\'re just testing user set creation with child user sets!',
            'parent'  => $newobj->id
        );

        $subuserset = new userset($data);
        $subuserset->save();

        // Update the sub-set, setting its parent to the top level
        $subuserset->parent = 0;
        $subuserset->save();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that the current state of the context record is valid
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to validate this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a child User Set context instance is updated when that User Set
     * has its parent changes to another valid user set
     */
    public function testChildUsersetContextOnParentChange() {
        //need to clean caching because it was causing the parent userset's
        //id to not have a matching context instanceid
        global $UNITTEST;
        $UNITTEST->running = true;
        accesslib_clear_all_caches_for_unit_testing();
        unset($UNITTEST->running);

        $firstparent = $this->initUserSet();
        $firstparent->save();

        $secondparent = new userset(array('name' => 'Second Parent'));
        $secondparent->save();

        $ctx1 = context_elis_userset::instance($secondparent->id);

        $data = array(
            'name'    => 'Test Sub User Set 1A',
            'display' => 'We\'re just testing user set creation with child user sets!',
            'parent'  => $firstparent->id
        );

        $subuserset = new userset($data);
        $subuserset->save();

        // Update the sub-set, setting its parent to the other top-level user set
        $subuserset->parent = $secondparent->id;
        $subuserset->save();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that the curent state of the context record is valid
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to valid that this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$ctx1->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a child User Set context instance is updated when that User Set
     * is promoted to the top level due to parent User Set deletion
     */
    public function testChildUsersetContextOnPromotionDuringParentDeletion() {
        $newobj = $this->initUserset();
        $newobj->save();

        $ctx1 = context_elis_userset::instance($newobj->id);

        $data = array(
            'name'    => 'Test Sub User Set 1A',
            'display' => 'We\'re just testing user set creation with child user sets!',
            'parent'  => $newobj->id
        );

        $subuserset = new userset($data);
        $subuserset->save();

        // Delete the parent user set, promoting the sub-user-set
        $newobj->delete();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that the curent state of the context record is valid
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to valid that this in the returned context object
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }
}
