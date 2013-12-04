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
require_once(elis::file('program/healthpage.class.php'));
require_once(elis::plugin_file('eliscoreplugins_user_activity', 'health.php'));
require_once(elis::plugin_file('eliscoreplugins_user_activity', 'etl.php'));

/**
 * Test health checks.
 * @group elis_program
 */
class user_activity_health_testcase extends elis_database_test {

    /**
     * Test the user_activity_health_check
     */
    public function test_etlbehindmorethanweek() {
        global $DB;

        $dataset = $this->createCsvDataSet(array(
            'log' => elis::file('program/tests/fixtures/log_data.csv')
        ));
        $this->loadDataSet($dataset);

        elis::$config->eliscoreplugins_user_activity->last_run = time() - DAYSECS;
        elis::$config->eliscoreplugins_user_activity->state = serialize(array(
            'sessiontimeout' => 300,
            'sessiontail' => 300,
            'starttime' => 1326308749,
            'startrec' => 1
        ));

        $problem = new user_activity_health_empty();
        $this->assertTrue($problem->exists());

        elis::$config->eliscoreplugins_user_activity->state = serialize(array(
            'sessiontimeout' => 300,
            'sessiontail' => 300,
            'starttime' => time() - (6 * DAYSECS),
            'startrec' => 1
        ));
        $this->assertFalse($problem->exists());
    }

    /**
     * Test ETL bugs fixed with ELIS-7815 & ELIS-7845
     */
    public function test_noetlerrorswithproblemlogdata() {
        global $DB;

        $dataset = $this->createCsvDataSet(array(
            'log' => elis::file('program/tests/fixtures/mdl_log_elis7845_1500.csv')
        ));
        $this->loadDataSet($dataset);

        elis::$config->eliscoreplugins_user_activity->last_run = 0;
        elis::$config->eliscoreplugins_user_activity->state = '';

        // Create existing record (NOT first)!
        $DB->insert_record('etl_user_module_activity', (object)array(
            'userid' => 409,
            'courseid' => 382,
            'cmid' => 12127,
            'hour' => 1319659200,
            'duration' => 1
        ));

        // Run until complete.
        $prevdone = 0;
        $prevtogo = 1501;
        $prevstart = 0;
        $etlobj = new etl_user_activity(0, false);
        do {
            $realtime = time();
            ob_start();
            $etlobj->cron();
            ob_end_clean();
            $lasttime = (int)$etlobj->state['starttime'];
            $recordsdone = $DB->count_records_select('log', "time < $lasttime");
            $recordstogo = $DB->count_records_select('log', "time >= $lasttime");
            /*
             * Uncomment to track progress.
             * echo "\n Done = {$recordsdone} ({$prevdone}), Togo = {$recordstogo} ({$prev_togo}),
             * starttime = {$lasttime} ({$prev_start})\n";
             */
            if (!$lasttime || !$recordstogo) {
                break;
            }
            $this->assertTrue($recordsdone >= $prevdone);
            $this->assertTrue($recordstogo <= $prevtogo);
            $this->assertTrue($lasttime > $prevstart);
            $prevdone = $recordsdone;
            $prevtogo = $recordstogo;
            $prevstart = $lasttime;
        } while (true);
        $etluacnt = $DB->count_records('etl_user_activity');
        $etlumacnt = $DB->count_records('etl_user_module_activity');

        $this->assertEquals(342, $etluacnt);
        $this->assertEquals(225, $etlumacnt);
    }

    public function test_duplicate_profile_data() {
        global $DB;
        require_once(elispm::file('healthpage.class.php'));

        // Set up data.
        $record = new stdClass;
        $record->fieldid = 1;
        $record->userid = 1;
        $record->data = 'test';
        $DB->insert_record('user_info_data', $record);
        $DB->insert_record('user_info_data', $record);
        $DB->insert_record('user_info_data', $record);
        $DB->insert_record('user_info_data', $record);

        $duplicateprofilecheck = new duplicate_moodle_profile();
        $this->assertEquals(get_string('health_dupmoodleprofiledesc', 'elis_program', 3), $duplicateprofilecheck->description());
    }
}
