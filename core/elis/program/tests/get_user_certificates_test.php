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
require_once(elispm::lib('data/certificateissued.class.php'));
require_once(elispm::file('tests/other/datagenerator.php'));

/**
 * PHPUnit test to retrieve a user's certificates
 * @group elis_program
 */
class get_user_certificates_testcase extends elis_database_test {

    /**
     * Setup PHPUnit CSV data
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            certificatesettings::TABLE => elis::component_file('program', 'tests/fixtures/certificate_settings.csv'),
            certificateissued::TABLE => elis::component_file('program', 'tests/fixtures/certificate_issued.csv')
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Test retreiving a user who has been issued only 1 certificate referencing only 1 certificate setting record
     */
    public function test_retrieve_user_cert_for_one_class() {
        $this->load_csv_data();

        $record    = array();
        $cmuserid = 2;
        $recordset = get_user_certificates($cmuserid);

        $expected  = array();
        $expected['id'] = 1;
        $expected['cm_userid'] = '2';
        $expected['cert_code'] = '279Fjap8j6oPKnw';
        $expected['timeissued'] = '1357851284';
        $expected['entity_id'] = '1';
        $expected['entity_type'] = 'COURSE';
        $expected['csid'] = '1';
        $expected['cert_border'] = 'Fancy1-black.jpg';
        $expected['cert_seal'] = 'Fancy.png';
        $expected['cert_template'] = 'default.php';

        foreach ($recordset as $data) {
            $record[] = $data;
        }

        $recordset->close();

        $this->assertEquals(1, count($record));

        foreach ($expected as $key => $val) {
            $this->assertObjectHasAttribute($key, $record[0]);
            $this->assertEquals($val, $record[0]->$key);
        }
    }

    /**
     * Test retreiving a user who has been issued a certificate with illegit data
     */
    public function test_retrieve_user_cert_for_one_class_fail() {
        $this->load_csv_data();

        $record    = array();
        $cmuserid = 99;
        $recordset = get_user_certificates($cmuserid);

        foreach ($recordset as $data) {
            $record[] = $data;
        }

        $recordset->close();

        $this->assertEquals(0, count($record));
    }

    /**
     * Test retreiving a user who has been issued 2 certificate referencing only 2 different certificate setting record
     * (example.  A student who completed a class instance of one course description and completed different class instance
     * belonging to a different course description)
     */
    public function test_retrieve_user_cert_for_two_class_two_settings() {
        $this->load_csv_data();

        $record    = array();
        $cmuserid = 4;
        $recordset = get_user_certificates($cmuserid);

        $expected  = array();
        $expected['id'] = 4;
        $expected['cm_userid'] = '4';
        $expected['cert_code'] = '579Fjap8j6oPKnw';
        $expected['timeissued'] = '1358313000';
        $expected['entity_id'] = '3';
        $expected['entity_type'] = 'COURSE';
        $expected['csid'] = '3';
        $expected['cert_border'] = 'Fancy1-green.jpg';
        $expected['cert_seal'] = 'Quality.png';
        $expected['cert_template'] = 'default3.php';

        $expected2  = array();
        $expected2['id'] = 5;
        $expected2['cm_userid'] = '4';
        $expected2['cert_code'] = '679Fjap8j6oPKnw';
        $expected2['timeissued'] = '1358315000';
        $expected2['entity_id'] = '4';
        $expected2['entity_type'] = 'COURSE';
        $expected2['csid'] = '4';
        $expected2['cert_border'] = 'Fancy1-red.jpg';
        $expected2['cert_seal'] = 'horrid.png';
        $expected2['cert_template'] = 'default4.php';

        foreach ($recordset as $data) {
            $record[] = $data;
        }

        $recordset->close();

        $this->assertEquals(2, count($record));

        foreach ($expected as $key => $val) {
            $this->assertObjectHasAttribute($key, $record[0]);
            $this->assertEquals($val, $record[0]->$key);
        }

        foreach ($expected2 as $key => $val) {
            $this->assertObjectHasAttribute($key, $record[1]);
            $this->assertEquals($val, $record[1]->$key);
        }
    }

    /**
     * Test retreiving a user who has been issued 2 certificate referencing only 1 certificate setting record
     * (example.  A student, enrolled in 2 different classes belonging to the same course description BUT the certificate has
     * been disabled)
     */
    public function test_retrieve_user_cert_for_two_class_one_settings_disabled() {
        $this->load_csv_data();

        $record    = array();
        $cmuserid  = 3;
        $recordset = get_user_certificates($cmuserid);

        foreach ($recordset as $data) {
            $record[] = $data;
        }

        $recordset->close();

        $this->assertEquals(0, count($record));
    }

    /**
     * Test retreiving a user who has been issued 2 certificate referencing only 1 certificate setting record
     * (example.  A student, enrolled in 2 different classes belonging to the same course description)
     */
    public function test_retrieve_user_cert_for_two_class_one_settings() {
        $this->load_csv_data();

        $record    = array();
        $cmuserid = 5;
        $recordset = get_user_certificates($cmuserid);

        $expected  = array();
        $expected['id'] = 6;
        $expected['cm_userid'] = '5';
        $expected['cert_code'] = '779Fjap8j6oPKnw';
        $expected['timeissued'] = '1358316000';
        $expected['entity_id'] = '5';
        $expected['entity_type'] = 'COURSE';
        $expected['csid'] = '5';
        $expected['cert_border'] = 'Fancy1-black.jpg';
        $expected['cert_seal'] = 'Amazing.png';
        $expected['cert_template'] = 'default5.php';

        $expected2  = array();
        $expected2['id'] = 7;
        $expected2['cm_userid'] = '5';
        $expected2['cert_code'] = '879Fjap8j6oPKnw';
        $expected2['timeissued'] = '1358317000';
        $expected2['entity_id'] = '5';
        $expected2['entity_type'] = 'COURSE';
        $expected2['csid'] = '5';
        $expected2['cert_border'] = 'Fancy1-black.jpg';
        $expected2['cert_seal'] = 'Amazing.png';
        $expected2['cert_template'] = 'default5.php';

        foreach ($recordset as $data) {
            $record[] = $data;
        }

        $recordset->close();

        $this->assertEquals(2, count($record));

        foreach ($expected as $key => $val) {
            $this->assertObjectHasAttribute($key, $record[0]);
            $this->assertEquals($val, $record[0]->$key);
        }

        foreach ($expected2 as $key => $val) {
            $this->assertObjectHasAttribute($key, $record[1]);
            $this->assertEquals($val, $record[1]->$key);
        }
    }
}