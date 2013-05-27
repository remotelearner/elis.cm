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
 * @package    elis
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('lib.php'));

class pm_gmt_from_usertime_test extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * Method to specify required overlay tables
     * @return array required overlay tables
     */
    protected static function get_overlay_tables() {
        return array();
    }

    /**
     * test_pm_gmt_from_usertime() data provider
     * values format: array( inputtimestamp, timezone, outputtimestamp)
     * @return array the test data
     */
    public function pm_gmt_from_usertime_dataprovider() {
        return array(
            array(12345678, 0, 12345678),
            array(12345678, 14, 12345678),
            array(12345678, 13.1, 12345678),
            array(100000, 12, 100000 - (12 * HOURSECS)),
            array(100000, -12, 100000 - (-12 * HOURSECS)),
            array(100000, 12.5, 100000 - (12.5 * HOURSECS)),
            array(1366619400, 'America/Toronto', 1366633800), // w/ DST
            array(1385112600, 'America/Toronto', 1385130600), // w/o DST
        );
    }

    /**
     * Test pm_gmt_from_usertime() function
     * @param int $intimestamp the input timestamp
     * @param float|int|string $timezone the timezone of $intimestamp
     * @param int $outtimestamp the GMT timestamp for $intimestamp in $timezone
     * @dataProvider pm_gmt_from_usertime_dataprovider
     */
    public function test_pm_gmt_from_usertime($intimestamp, $timezone, $outtimestamp) {
        if (get_user_timezone_offset($timezone) == 99) {
            $this->markTestSkipped("\nSkipping test_pm_gmt_from_usertime() with undefined timezone = '{$timezone}'\n");
        } else {
            $this->assertEquals($outtimestamp, pm_gmt_from_usertime($intimestamp, $timezone));
        }
    }
}
