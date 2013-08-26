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
require_once(elispm::lib('data/certificatesettings.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * PHPUnit test to retrieve a certificate settings record by entity id and type
 * @group elis_program
 */
class get_certificate_settings_by_entity_testcase extends elis_database_test {

    /**
     * Load PHPUnit test data
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            certificatesettings::TABLE => elis::component_file('program', 'tests/fixtures/certificate_settings.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test retrieving a certficate setting id using legit entity id and entity type values
     */
    public function test_retrieve_certificate_settings_by_entity() {
        $this->load_csv_data();

        // Test Retrieving legit data.
        $entityid   = '3';
        $entitytype = 'COURSE';

        $certsettings = new certificatesettings();
        $certsettings->get_data_by_entity($entityid, $entitytype);
        $record = $certsettings->to_object();

        $this->assertObjectHasAttribute('id', $record);
        $this->assertEquals('3', $record->id);

    }

    /**
     * Test retrieving a certificate setting by using in illegit entity id
     */
    public function test_retrieve_certificate_settings_by_entity_id_fail() {
        $this->load_csv_data();

        // Test Retrieving illegit data.
        $entityid   = 99;
        $entitytype = 'COURSE';

        $certsettings = new certificatesettings();
        $certsettings->get_data_by_entity($entityid, $entitytype);
        $record = $certsettings->to_object();

        $this->assertObjectNotHasAttribute('id', $certsettings);

    }

    /**
     * Test retreiving a certificate setting by using in illegit entity type value
     */
    public function test_retrieve_certificate_settings_by_entity_type_fail() {
        $this->load_csv_data();

        // Test Retrieving illegit data.
        $entityid   = 3;
        $entitytype = 'NOTHING';

        $certsettings = new certificatesettings();
        $certsettings->get_data_by_entity($entityid, $entitytype);
        $record = $certsettings->to_object();

        $this->assertObjectNotHasAttribute('id', $certsettings);
    }
}