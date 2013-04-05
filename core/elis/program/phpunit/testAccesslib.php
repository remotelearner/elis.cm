<?php

/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage core
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

//data objects
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/coursetemplate.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::lib('data/student.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

class accesslibTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'context' => 'moodle',
            'user' => 'moodle',
            course::TABLE => 'elis_program',
            coursetemplate::TABLE => 'elis_program',
            curriculum::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
            student::TABLE => 'elis_program',
            track::TABLE => 'elis_program',
            trackassignment::TABLE => 'elis_program',
            user::TABLE => 'elis_program',
            usermoodle::TABLE => 'elis_program',
            userset::TABLE => 'elis_program',
        );
    }

    public function create_entities() {
        global $DB;
        $elis_gen = new elis_program_datagenerator($DB);

        $program = $elis_gen->create_program();
        $track = $elis_gen->create_track(array(
            'curid' => $program->id
        ));
        $course = $elis_gen->create_course();
        $pmclass = $elis_gen->create_pmclass(array(
            'courseid' => $course->id,
            'track' => array($track->id),
        ));
        $userset = $elis_gen->create_userset();
        $user = $elis_gen->create_user();
        $student = $elis_gen->assign_user_to_class($user,$pmclass);

        return array($program,$track,$course,$pmclass,$user,$userset);
    }

    /**
     * This creates a userset, sub userset, and sub-sub userset to test child contexts
     */
    public function create_usersets() {
        global $DB;

        $elis_gen = new elis_program_datagenerator($DB);

        $userset = $elis_gen->create_userset();
        $userset_ctx = context_elis_userset::instance($userset->id);

        $subuserset = $elis_gen->create_userset(array(
            'parent' => $userset->id,
            'depth' => 2
        ));
        $subuserset_ctx = context_elis_userset::instance($subuserset->id);

        $subsubuserset = $elis_gen->create_userset(array(
            'parent' => $subuserset->id,
            'depth' => 3
        ));
        $subsubuserset_ctx = context_elis_userset::instance($subsubuserset->id);

        return array($userset,$subuserset,$subsubuserset);
    }

    public function set_up_entities() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculum::TABLE, elis::component_file('program', 'phpunit/curriculum.csv'));
        $dataset->addTable(track::TABLE, elis::component_file('program', 'phpunit/track.csv'));
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcourse.csv'));
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));
        $dataset->addTable(user::TABLE, elis::component_file('program', 'phpunit/pmuser.csv'));
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        $dataset->addTable('context', elis::component_file('program', 'phpunit/test_accesslib_contexts.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    public function test_program_child_contexts() {
        context_helper::reset_caches();

        $this->set_up_entities();

        $ctx = context_elis_program::instance(1);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $this->assertTrue(($child instanceof context_elis_track));
            $this->assertEquals(1,$child->instanceid);
            $count++;
            break;
        }
        $this->assertEquals(1,$count);
    }

    public function test_track_child_contexts() {
        context_helper::reset_caches();

        $this->set_up_entities();

        $ctx = context_elis_track::instance(1);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $count++;
        }
        $this->assertEquals(0,$count);
    }

    public function test_course_child_contexts() {
        context_helper::reset_caches();

        $this->set_up_entities();

        $ctx = context_elis_course::instance(100);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $this->assertTrue(($child instanceof context_elis_class));
            $this->assertEquals(100,$child->instanceid);
            $count++;
            break;
        }
        $this->assertEquals(1,$count);
    }

    public function test_pmclass_child_contexts() {
        context_helper::reset_caches();

        $this->set_up_entities();

        $ctx = context_elis_class::instance(100);
        $children = $ctx->get_child_contexts();
        $count = 0;
        foreach ($children as $child) {
            $count++;
        }
        $this->assertEquals(0,$count);
    }

    public function test_userset_child_contexts() {
        context_helper::reset_caches();

        $this->set_up_entities();

        $expected = array(
            1 => array(2,3,4),
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
            $this->assertEquals(sizeof($childids),$count);
        }
    }
}