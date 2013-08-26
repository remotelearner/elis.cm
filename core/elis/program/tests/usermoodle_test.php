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
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Test usermoodle functions.
 * @group elis_program
 */
class usermoodle_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            usermoodle::TABLE => elis::component_file('program', 'tests/fixtures/user_moodle.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test validation of duplicates
     * @expectedException data_object_validation_exception
     */
    public function test_usermoodlepreventsduplicates() {
        $this->load_csv_data();
        $usermoodle = new usermoodle(array('cuserid' => 103, 'muserid' => 100, 'idnumber' => 'anotheridnumber'));
        $usermoodle->save();
    }

    /**
     * Test validation of duplicates
     * @expectedException data_object_validation_exception
     */
    public function test_usermoodlepreventsduplicateidnumber() {
        $this->load_csv_data();
        $usermoodle = new usermoodle(array('cuserid' => 2, 'muserid' => 2, 'idnumber' => '__idnumber__phpunit_test2__'));
        $usermoodle->save();
    }
}