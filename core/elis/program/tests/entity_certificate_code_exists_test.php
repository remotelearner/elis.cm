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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once(elispm::lib('data/certificateissued.class.php'));
require_once(elispm::lib('data/curriculumstudent.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * PHPUnit test to retrieve a user's certificates
 * @group elis_program
 */
class entity_certificate_code_exists_testcase extends elis_database_test {

    /**
     * Load PHPUnit test data
     */
    protected function load_csv_data() {
        // Load initial data from a CSV file.
        $dataset = $this->createCsvDataSet(array(
            certificateissued::TABLE => elis::component_file('program', 'tests/fixtures/certificate_issued.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test whether a certificate code already exists in the table and true is returned by the function
     */
    public function test_entity_certificate_code_exists() {
        $this->load_csv_data();

        $result = entity_certificate_code_exists('279Fjap8j6oPKnw');

        $this->assertEquals(true, $result);
    }

    /**
     * Test whether a certificate code does not exist in the table and false is returned by the function
     */
    public function test_entity_certificate_code_not_exists() {
        $this->load_csv_data();

        $result = entity_certificate_code_exists('279Fjap8j6oPKnw9');

        $this->assertEquals(false, $result);
    }

    /**
     * Test whether true is returned when an empty value is passed as an argument
     */
    public function test_entity_certificate_code_no_code() {
        $this->load_csv_data();

        $result = entity_certificate_code_exists('');

        $this->assertEquals(true, $result);
    }
}