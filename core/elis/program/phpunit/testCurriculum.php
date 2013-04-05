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
 * @subpackage program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elispm::lib('data/clustercurriculum.class.php'));
require_once($CFG->dirroot . '/elis/program/curriculumpage.class.php');
require_once($CFG->dirroot . '/lib/phpunittestlib/testlib.php');
require_once(elis::lib('testlib.php'));

class testCurriculum extends elis_database_test {

    protected static function get_overlay_tables() {
        return array(
            'user' => 'moodle',
            curriculum::TABLE => 'elis_program',
            clustercurriculum::TABLE => 'elis_program'
        );
    }

    /*
    * Test that a user without program_view permissions does not get any data returned
    */
    public function testNoCurriculumPermission() {
        $this->load_csv_data();

        $curriculum_filter = array('contexts' => curriculumpage::get_contexts('elis/program:program_view'));

        try {
            $curricula = clustercurriculum::get_curricula(1, 0, 5, 'cur.priority ASC, cur.name ASC', $curriculum_filter);
        } catch(Exception $e) {
            error_log("Failed database call from get_curricula: " . $e);
        }

        $emptyarray = array();
        $this->assertEquals($emptyarray, $curricula);
    }

    protected function load_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(curriculum::TABLE, dirname(__FILE__).'/curriculum.csv');
        $dataset->addTable(clustercurriculum::TABLE, dirname(__FILE__).'/clustercurriculum.csv');
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

}
?>
