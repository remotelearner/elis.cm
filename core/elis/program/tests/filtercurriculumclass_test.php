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
require_once(elispm::lib('filtering/curriculumclass.php'));
require_once(elispm::lib('data/course.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/pmclass.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * Test the curriculumclass filter.
 * @group elis_program
 */
class filtercurriculumclass_testcase extends elis_database_test {

    /**
     * Test make_filter_options_custom function
     */
    public function test_make_filter_options_custom() {
        global $DB;

        // Fixture.
        $datagenerator = new elis_program_datagenerator($DB);
        $pgm = $datagenerator->create_program();
        $course = $datagenerator->create_course();
        $pmclass = $datagenerator->create_pmclass(array('courseid' => $course->id));

        $dataset = $this->createCsvDataSet(array(
            'crlm_environment' => elis::component_file('program', 'tests/fixtures/environment.csv')
        ));
        $this->loadDataSet($dataset);

        // Tests.
        $choicestests = array(
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

        foreach ($choicestests as $group => $names) {
            foreach ($names as $name => $expectedchoices) {
                $curclassopts = array(
                    'choices' => array(),
                    'wrapper' => array(
                        $group => '',
                    )
                );
                $filter = new generalized_filter_curriculumclass('filt-curriculumclass', 'Null', $curclassopts);
                $options = $filter->make_filter_options_custom(array(), $group, $name);
                $this->assertInternalType('array', $options);
                $this->assertArrayHasKey('choices', $options);
                $this->assertInternalType('array', $options['choices']);
                $this->assertEquals($expectedchoices, $options['choices']);
                unset($filter);
            }
        }
    }
}