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
require_once(elispm::file('enrol/userset/moodle_profile/userset_profile.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));
require_once(elispm::lib('data/userset.class.php'));

/**
 * Test element creation.
 * @group elis_program
 */
class elementcreation_testcase extends elis_database_test {

    /**
     * Initialize a new program object.
     * @return curriculum The new program object
     */
    protected function initprogram() {
        return new curriculum(array('idnumber' => 'TESTID001', 'name' => 'Test Program 1'));
    }

    /**
     * Initialize a new track object.
     * @param int $curid A curriculum record ID
     * @return track The new track object
     */
    protected function inittrack($curid) {
        $data = array(
            'curid' => $curid,
            'idnumber' => 'TESTID001',
            'name' => 'Test Track 1'
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
            'name' => 'Test Course 1',
            'syllabus' => ''
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
            'idnumber' => 'TESTID001',
            'username' => 'testuser1',
            'firstname' => 'Test',
            'lastname' => 'User1',
            'email' => 'testuser1@example.com',
            'country' => 'us'
        );
        return new user($data);
    }

    /**
     * Initialize a new user description object
     * @return userset The new userset object
     */
    private function inituserset() {
        $data = array(
            'name' => 'Test User Set 1',
            'display' => 'We\'re just testing user set creation!'
        );
        return new userset($data);
    }

    /**
     * Test that a new Program instance can be created and saved to the database.
     */
    public function test_createprogram() {
        $newobj = $this->initprogram();
        $newobj->save();

        // Verify that the object was saved to the database and the record ID was assigned to the object.
        $this->assertGreaterThan(0, $newobj->id);

        // Fetch the record from the database.
        $testobj = new curriculum($newobj->id);

        // Verify that the record returned from the database matches what was inserted.
        $this->assertEquals($newobj->id, $testobj->id);
        $this->assertEquals($newobj->idnumber, $testobj->idnumber);
        $this->assertEquals($newobj->name, $testobj->name);
    }

    /**
     * Test that a new Track instance can be created and saved to the database.
     */
    public function test_createtrack() {
        $newprogram = $this->initprogram();
        $newprogram->save();

        $newobj = $this->inittrack($newprogram->id);
        $newobj->save();

        $this->assertGreaterThan(0, $newobj->id);

        // Fetch the record from the database.
        $testobj = new track($newobj->id);

        // Verify that the record returned from the database matches what was inserted.
        $this->assertEquals($newobj->id, $testobj->id);
        $this->assertEquals($newobj->idnumber, $testobj->idnumber);
        $this->assertEquals($newobj->name, $testobj->name);
    }

    /**
     * Test that a new Course instance can be created and saved to the database.
     */
    public function test_createcourse() {
        $newobj = $this->initcourse();
        $newobj->save();

        $this->assertGreaterThan(0, $newobj->id);

        // Fetch the record from the database.
        $testobj = new course($newobj->id);

        // Verify that the record returned from the database matches what was inserted.
        $this->assertEquals($newobj->id, $testobj->id);
        $this->assertEquals($newobj->idnumber, $testobj->idnumber);
        $this->assertEquals($newobj->name, $testobj->name);
    }

    /**
     * Test that a new Class instance can be created and saved to the database.
     */
    public function test_createclass() {
        $newcourse = $this->initcourse();
        $newcourse->save();

        $newobj = $this->initclass($newcourse->id);
        $newobj->save();

        $this->assertGreaterThan(0, $newobj->id);

        // Fetch the record from the database.
        $testobj = new pmclass($newobj->id);

        // Verify that the record returned from the database matches what was inserted.
        $this->assertEquals($newobj->id, $testobj->id);
        $this->assertEquals($newobj->idnumber, $testobj->idnumber);
    }

    /**
     * Test that a new User instance can be created and saved to the database.
     */
    public function test_createuser() {
        $newobj = $this->inituser();
        $newobj->save();

        $this->assertGreaterThan(0, $newobj->id);

        // Fetch the record from the database.
        $testobj = new user($newobj->id);

        // Verify that the record returned from the database matches what was inserted.
        $this->assertEquals($newobj->id, $testobj->id);
        $this->assertEquals($newobj->idnumber, $testobj->idnumber);
        $this->assertEquals($newobj->username, $testobj->username);
        $this->assertEquals($newobj->firstname, $testobj->firstname);
        $this->assertEquals($newobj->lastname, $testobj->lastname);
        $this->assertEquals($newobj->email, $testobj->email);
        $this->assertEquals($newobj->country, $testobj->country);
    }

    /**
     * Test that a new User Set instance can be created and saved to the database.
     */
    public function test_createuserset() {
        $newobj = $this->inituserset();
        $newobj->save();

        $this->assertGreaterThan(0, $newobj->id);

        // Fetch the record from the database.
        $testobj = new userset($newobj->id);

        // Verify that the record returned from the database matches what was inserted.
        $this->assertEquals($newobj->id, $testobj->id);
        $this->assertEquals($newobj->name, $testobj->name);
        $this->assertEquals($newobj->display, $testobj->display);
    }
}