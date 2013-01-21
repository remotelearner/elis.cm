<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage user_activity
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elis::file('program/healthpage.class.php'));
require_once(elis::plugin_file('eliscoreplugins_user_activity', 'health.php'));
require_once(elis::plugin_file('eliscoreplugins_user_activity', 'etl.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

class user_activity_health_test extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'log' => 'moodle',
            'config_plugins' => 'moodle',
            'etl_user_activity' => 'eliscoreplugins_user_activity',
            'etl_user_module_activity' => 'eliscoreplugins_user_activity',
            'user_info_data' => 'moodle',
        );
    }

    /**
     * Test the user_activity_health_check
     */
    public function testETLbehindmorethanweek() {
        global $DB;
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('log', elis::file('program/phpunit/log_data.csv'));

        $overlaydb = self::$overlaydb;
        load_phpunit_data_set($dataset, true, $overlaydb);

        elis::$config->eliscoreplugins_user_activity->last_run = time() - DAYSECS;
        elis::$config->eliscoreplugins_user_activity->state = serialize(array(
                 "sessiontimeout" => 300,
                 "sessiontail" => 300,
                 "starttime" => 1326308749,
                 "startrec" => 1
            ));

        $problem = new user_activity_health_empty();
        $this->assertTrue($problem->exists());

        elis::$config->eliscoreplugins_user_activity->state = serialize(array(
                 "sessiontimeout" => 300,
                 "sessiontail" => 300,
                 "starttime" => time() - (6 * DAYSECS),
                 "startrec" => 1
            ));
        $this->assertFalse($problem->exists());
     }

    /**
     * Test ETL bugs fixed with ELIS-7815 & ELIS-7845
     */
    public function testNoETLerrorsWithProblemLogData() {
        global $DB;
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('log', elis::file('program/phpunit/mdl_log_elis7845_1500.csv'));

        $overlaydb = self::$overlaydb;
        load_phpunit_data_set($dataset, true, $overlaydb);

        elis::$config->eliscoreplugins_user_activity->last_run = 0;
        elis::$config->eliscoreplugins_user_activity->state = '';

        // Create existing record (NOT first)!
        $DB->insert_record('etl_user_module_activity',
                   (object)array('userid'   => 409,
                                 'courseid' => 382,
                                 'cmid'     => 12127,
                                 'hour'     => 1319659200,
                                 'duration' => 1)
                          );

        // Run until complete
        $prev_done = 0;
        $prev_togo = 1501;
        $prev_start = 0;
        echo "\n";
        do {
            $realtime = time();
            user_activity_etl_cron();
            $state = user_activity_task_init(false);
            $last_time = (int)$state['starttime'];
            $records_done = $DB->count_records_select('log', "time < $last_time");
            $records_togo = $DB->count_records_select('log', "time >= $last_time");
            //echo "\n Done = {$records_done} ({$prev_done}), Togo = {$records_togo} ({$prev_togo}), starttime = {$last_time} ({$prev_start})\n";
            if (!$last_time || !$records_togo) {
                break;
            }
            $this->assertTrue($records_done >= $prev_done);
            $this->assertTrue($records_togo <= $prev_togo);
            $this->assertTrue($last_time > $prev_start);
            $prev_done = $records_done;
            $prev_togo = $records_togo;
            $prev_start = $last_time;
        } while (TRUE);
        $etluacnt = $DB->count_records('etl_user_activity');
        $etlumacnt = $DB->count_records('etl_user_module_activity');
        //echo "\nETLUAcnt = {$etluacnt}";
        //echo "\nETLUMAcnt = {$etlumacnt}";
      /*
        $recs = $DB->get_records('etl_user_module_activity');
        $cnt = 0;
        foreach ($recs AS $rec) {
            $cnt++;
            echo "\n Rec #{$cnt} etl_user_module_activity = ";
            var_dump($rec);
            if ($cnt > 10) break;
        }
      */
        $this->assertEquals(342, $etluacnt);
        $this->assertEquals(225, $etlumacnt);
    }

    public function test_duplicate_profile_data() {
        global $DB;
        require_once(elispm::file('healthpage.class.php'));

        //set up data
        $record = new stdClass;
        $record->fieldid = 1;
        $record->userid = 1;
        $record->data = 'test';
        $DB->insert_record('user_info_data',$record);
        $DB->insert_record('user_info_data',$record);
        $DB->insert_record('user_info_data',$record);
        $DB->insert_record('user_info_data',$record);

        $duplicate_profile_check = new duplicate_moodle_profile();
        $this->assertEquals(
                get_string('health_dupmoodleprofiledesc', 'elis_program', 3),
                $duplicate_profile_check->description()
        );

    }
}
