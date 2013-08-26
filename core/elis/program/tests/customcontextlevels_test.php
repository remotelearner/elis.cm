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

/**
 * Test ELIS custom context levels.
 * @group elis_program
 */
class customcontextlevels_testcase extends elis_database_test {

    /**
     * Initialize a new program object.
     * @return curriculum The new program object
     */
    protected function initprogram() {
        $data = array(
            'idnumber' => 'TESTID001',
            'name'     => 'Test Program 1'
        );

        return new curriculum($data);
    }

    /**
     * Initialize a new track object.
     * @param int $curid A curriculum record ID
     * @return track The new track object
     */
    protected function inittrack($curid) {
        $data = array(
            'curid'    => $curid,
            'idnumber' => 'TESTID001',
            'name'     => 'Test Track 1'
        );

        return new track($data);
    }

    /**
     * Initialize a new course description object.
     * @return course The new course object
     */
    protected function initcourse() {
        $data = array(
            'idnumber' => 'TESTID001',
            'name'     => 'Test Course 1',
            'syllabus' => ''  // For some reason this field needs to be defined, or INSERT fails?!
        );

        return new course($data);
    }

    /**
     * Initialize a new class object.
     * @param int $courseid A course record ID
     * @return class The new class object
     */
    protected function initclass($courseid) {
        $data = array(
            'idnumber' => 'TESTID001',
            'courseid' => $courseid
        );

        return new pmclass($data);
    }

    /**
     * Initialize a new user description object.
     * @return user The new user object
     */
    protected function inituser() {
        $data = array(
            'idnumber'  => 'TESTID001',
            'username'  => 'testuser1',
            'firstname' => 'Test',
            'lastname'  => 'User1',
            'email'     => 'testuser1@example.com',
            'country'   => 'us'
        );

        return new user($data);
    }

    /**
     * Initialize a new user description object.
     * @return userset The new userset object
     */
    protected function inituserset() {
        $data = array(
            'name'    => 'Test User Set 1',
            'display' => 'We\'re just testing user set creation!'
        );

        return new userset($data);
    }

    /**
     * Test that a new Program context instance can be created and saved to the database.
     */
    public function test_programcontext() {
        $newobj = $this->initprogram();
        $newobj->save();

        $context = context_elis_program::instance($newobj->id);

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_PROGRAM, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new Track context instance can be created and saved to the database.
     */
    public function test_trackcontext() {
        $program = $this->initprogram();
        $program->save();

        $newobj = $this->inittrack($program->id);
        $newobj->save();

        $context = context_elis_track::instance($newobj->id);

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_TRACK, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $pctx = context_elis_program::instance($program->id);
        $path = '/'.SYSCONTEXTID.'/'.$pctx->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new Course context instance can be created and saved to the database.
     */
    public function test_coursecontext() {
        $newobj = $this->initcourse();
        $newobj->save();

        $context = context_elis_course::instance($newobj->id);

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_COURSE, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new Class context instance can be created and saved to the database.
     */
    public function test_classcontext() {
        $course = $this->initcourse();
        $course->save();

        $newobj = $this->initclass($course->id);
        $newobj->save();

        $context = context_elis_class::instance($newobj->id);

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_CLASS, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $cctx = context_elis_course::instance($course->id);
        $path = '/'.SYSCONTEXTID.'/'.$cctx->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new User context instance can be created and saved to the database.
     */
    public function test_usercontext() {
        $newobj = $this->inituser();
        $newobj->save();

        $context = context_elis_user::instance($newobj->id);

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USER, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new parent User Set context instance can be created and saved to the database.
     */
    public function test_parentusersetcontext() {
        $newobj = $this->inituserset();
        $newobj->save();

        $context = context_elis_userset::instance($newobj->id);

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($newobj->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a new parent User Set context instance can be created and saved to the database.
     */
    public function test_childusersetcontext() {
        $newobj = $this->inituserset();
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

        // Validate that a context record was actually created with correct values.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$ctx1->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a child User Set context instance is updated when that User Set is promoted to the top level.
     */
    public function test_childusersetcontext_onpromotion() {
        $newobj = $this->inituserset();
        $newobj->save();

        $ctx1 = context_elis_userset::instance($newobj->id);

        $data = array(
            'name'    => 'Test Sub User Set 1A',
            'display' => 'We\'re just testing user set creation with child user sets!',
            'parent'  => $newobj->id
        );

        $subuserset = new userset($data);
        $subuserset->save();

        // Update the sub-set, setting its parent to the top level.
        $subuserset->parent = 0;
        $subuserset->save();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that the current state of the context record is valid.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to validate this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a child User Set context instance is updated when that User Set has its parent changes to another valid user set.
     */
    public function test_childusersetcontext_onparentchange() {
        // Need to clean caching because it was causing the parent userset's id to not have a matching context instanceid.
        accesslib_clear_all_caches_for_unit_testing();

        $firstparent = $this->inituserSet();
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

        // Update the sub-set, setting its parent to the other top-level user set.
        $subuserset->parent = $secondparent->id;
        $subuserset->save();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that the curent state of the context record is valid.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to valid that this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$ctx1->id.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }

    /**
     * Test that a child User Set context instance is updated when that User Set is promoted to the top level due to parent
     * User Set deletion
     */
    public function test_childusersetcontext_onpromotion_duringparentdeletion() {
        $newobj = $this->inituserset();
        $newobj->save();

        $ctx1 = context_elis_userset::instance($newobj->id);

        $data = array(
            'name'    => 'Test Sub User Set 1A',
            'display' => 'We\'re just testing user set creation with child user sets!',
            'parent'  => $newobj->id
        );

        $subuserset = new userset($data);
        $subuserset->save();

        // Delete the parent user set, promoting the sub-user-set.
        $newobj->delete();

        $context = context_elis_userset::instance($subuserset->id);

        // Validate that the curent state of the context record is valid.
        $this->assertGreaterThan(0, $context->id);
        $this->assertEquals(CONTEXT_ELIS_USERSET, $context->contextlevel);
        $this->assertEquals($subuserset->id, $context->instanceid);

        // Create the context path to valid that this in the returned context object.
        $path = '/'.SYSCONTEXTID.'/'.$context->id;

        $this->assertEquals($path, $context->path);
        $this->assertEquals(substr_count($path, '/'), $context->depth);
    }
}