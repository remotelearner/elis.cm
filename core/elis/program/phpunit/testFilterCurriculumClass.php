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

require_once(elispm::lib('filtering/curriculumclass.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));

require_once(elispm::file('phpunit/datagenerator.php'));

class filterCurriculumClassTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            course::TABLE => 'elis_program',
            'crlm_environment' => 'elis_program',
            curriculum::TABLE => 'elis_program',
            pmclass::TABLE => 'elis_program',
        );
    }

    public function test_make_filter_options_custom() {
        global $DB;

        //fixture
        $elis_gen = new elis_program_datagen_unit($DB);
        $pgm = $elis_gen->create_program();
        $course = $elis_gen->create_course();
        $pmclass = $elis_gen->create_pmclass(array('courseid' => $course->id));

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('crlm_environment', elis::component_file('program', 'phpunit/environment.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        //tests
        $choices_tests = array(
            'curriculum' => array(
                'name' => array($pgm->id => $pgm->name),
            ),
            'course' => array(
                'name' => array($course->id => $course->name)
            ),
            'class' => array(
                'idnumber' => array($pmclass->id => $pmclass->idnumber),
                'environmentid' => array(1 => 'Testing')
            )
        );

        foreach ($choices_tests as $group => $names) {
            foreach ($names as $name => $expected_choices) {
                $filter = new generalized_filter_curriculumclass('filt-curriculumclass', 'Null', array());
                $options = $filter->make_filter_options_custom(array(), $group, $name);
                $this->assertInternalType('array',$options);
                $this->assertArrayHasKey('choices',$options);
                $this->assertInternalType('array',$options['choices']);
                $this->assertEquals($expected_choices,$options['choices']);
                unset($filter);
            }
        }
    }

}