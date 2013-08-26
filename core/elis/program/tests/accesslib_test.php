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
require_once(elispm::file('accesslib.php'));

// Data objects.
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Test accesslib.php functions
 * @group elis_program
 */
class pm_accesslib_testcase extends elis_database_test {

    /**
     * Set up a program and track, with contexts.
     */
    public function fixture_program_track() {
        global $DB;
        context_helper::reset_caches();

        // Import program and track from CSV.
        $dataset = $this->createCsvDataSet(array(
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
        ));
        $this->loadDataSet($dataset);

        // Set up program context.
        $pgmctx = (object)array('contextlevel' => CONTEXT_ELIS_PROGRAM, 'instanceid' => 1, 'path' => '', 'depth' => 2);
        $pgmctx->id = $DB->insert_record('context', $pgmctx);
        $DB->update_record('context', (object)array('id' => $pgmctx->id, 'path' => '/1/'.$pgmctx->id));

        // Set up track context.
        $trkctx = (object)array('contextlevel' => CONTEXT_ELIS_TRACK, 'instanceid' => 1, 'path' => '', 'depth' => 3);
        $trkctx->id = $DB->insert_record('context', $trkctx);
        $DB->update_record('context', (object)array('id' => $trkctx->id, 'path' => '/1/'.$pgmctx->id.'/'.$trkctx->id));
    }

    /**
     * Set up a course and two classes, with contexts.
     */
    public function fixture_course_class() {
        global $DB;
        context_helper::reset_caches();

        // Import course and classes from CSV.
        $dataset = $this->createCsvDataSet(array(
            course::TABLE => elis::component_file('program', 'tests/fixtures/pmcourse.csv'),
            pmclass::TABLE => elis::component_file('program', 'tests/fixtures/pmclass.csv'),
        ));
        $this->loadDataSet($dataset);

        // Set up course context.
        $crsctx = (object)array('contextlevel' => CONTEXT_ELIS_COURSE, 'instanceid' => 100, 'path' => '', 'depth' => 2);
        $crsctx->id = $DB->insert_record('context', $crsctx);
        $DB->update_record('context', (object)array('id' => $crsctx->id, 'path' => '/1/'.$crsctx->id));

        // Set up class contexts.
        $cls1ctx = (object)array('contextlevel' => CONTEXT_ELIS_CLASS, 'instanceid' => 100, 'path' => '', 'depth' => 3);
        $cls1ctx->id = $DB->insert_record('context', $cls1ctx);
        $DB->update_record('context', (object)array('id' => $cls1ctx->id, 'path' => '/1/'.$crsctx->id.'/'.$cls1ctx->id));

        $cls2ctx = (object)array('contextlevel' => CONTEXT_ELIS_CLASS, 'instanceid' => 101, 'path' => '', 'depth' => 3);
        $cls2ctx->id = $DB->insert_record('context', $cls2ctx);
        $DB->update_record('context', (object)array('id' => $cls2ctx->id, 'path' => '/1/'.$crsctx->id.'/'.$cls2ctx->id));
    }

    /**
     * Set up a userset, two sub-usersets, and one sub-sub-userset, with contexts.
     */
    public function fixture_userset() {
        global $DB;
        context_helper::reset_caches();

        // Import usersets from CSV.
        $dataset = $this->createCsvDataSet(array(
            userset::TABLE => elis::component_file('program', 'tests/fixtures/userset.csv'),
        ));
        $this->loadDataSet($dataset);

        // Set up userset contexts.
        $uset1ctx = (object)array('contextlevel' => CONTEXT_ELIS_USERSET, 'instanceid' => 1, 'path' => '', 'depth' => 2);
        $uset1ctx->id = $DB->insert_record('context', $uset1ctx);
        $DB->update_record('context', (object)array('id' => $uset1ctx->id, 'path' => '/1/'.$uset1ctx->id));

        $uset2ctx = (object)array('contextlevel' => CONTEXT_ELIS_USERSET, 'instanceid' => 2, 'path' => '', 'depth' => 3);
        $uset2ctx->id = $DB->insert_record('context', $uset2ctx);
        $DB->update_record('context', (object)array('id' => $uset2ctx->id, 'path' => '/1/'.$uset1ctx->id.'/'.$uset2ctx->id));

        $uset3ctx = (object)array('contextlevel' => CONTEXT_ELIS_USERSET, 'instanceid' => 3, 'path' => '', 'depth' => 3);
        $uset3ctx->id = $DB->insert_record('context', $uset3ctx);
        $DB->update_record('context', (object)array('id' => $uset3ctx->id, 'path' => '/1/'.$uset1ctx->id.'/'.$uset3ctx->id));

        $uset4ctx = (object)array('contextlevel' => CONTEXT_ELIS_USERSET, 'instanceid' => 4, 'path' => '', 'depth' => 4);
        $uset4ctx->id = $DB->insert_record('context', $uset4ctx);
        $path = '/1/'.$uset1ctx->id.'/'.$uset2ctx->id.'/'.$uset4ctx->id;
        $DB->update_record('context', (object)array('id' => $uset4ctx->id, 'path' => $path));
    }

    /**
     * Test the get_child_contexts() function of context_elis_program
     */
    public function test_program_child_contexts() {
        $this->fixture_program_track();

        $ctx = context_elis_program::instance(1);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $this->assertTrue(($child instanceof context_elis_track));
            $this->assertEquals(1, $child->instanceid);
            $count++;
            break;
        }
        $this->assertEquals(1, $count);
    }

    /**
     * Test the get_child_contexts() function of context_elis_track
     */
    public function test_track_child_contexts() {
        $this->fixture_program_track();

        $ctx = context_elis_track::instance(1);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $count++;
        }
        $this->assertEquals(0, $count);
    }

    /**
     * Test the get_child_contexts() function of context_elis_course
     */
    public function test_course_child_contexts() {
        $this->fixture_course_class();

        $ctx = context_elis_course::instance(100);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $this->assertTrue(($child instanceof context_elis_class));
            $this->assertEquals(100, $child->instanceid);
            $count++;
            break;
        }
        $this->assertEquals(1, $count);
    }

    /**
     * Test the get_child_contexts() function of context_elis_class
     */
    public function test_pmclass_child_contexts() {
        $this->fixture_course_class();

        $ctx = context_elis_class::instance(100);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $count++;
        }
        $this->assertEquals(0, $count);
    }

    /**
     * Test the get_child_contexts() function of context_elis_userset
     */
    public function test_userset_child_contexts() {
        $this->fixture_userset();

        $expected = array(
            1 => array(2, 3, 4),
            2 => array(4),
            3 => array(),
            4 => array()
        );

        foreach ($expected as $parentid => $childids) {
            $ctx = context_elis_userset::instance($parentid);
            $children = $ctx->get_child_contexts();
            $count = 0;
            foreach ($children as $child) {
                if (!empty($childids)) {
                    $this->assertTrue(($child instanceof context_elis_userset));
                    $this->assertContains($child->instanceid, $childids);
                }
                $count++;
            }
            $this->assertEquals(count($childids), $count);
        }
    }
}