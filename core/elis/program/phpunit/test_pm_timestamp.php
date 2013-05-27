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

class pm_timestamp_test extends elis_database_test {
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
     * values format: array( array('hour' => hour, 'minute' => minute, 'second' => second, 'month' => month, 'day' => day, 'year' => year), timezone, outputtimestamp)
     * @return array the test data
     */
    public function pm_timestamp_dataprovider() {
        $today101gmt = gmmktime(1, 1, 0);
        $datetimeparts1 = array( // 1:01am today
            'hour'   => 1,
            'minute' => 1,
            'second' => 0,
            'month'  => null,
            'day'    => null,
            'year'   => null
        );
        $datetimeparts2 = array( // 2:03:04am Jan 1, 2013
            'hour'   => 2,
            'minute' => 3,
            'second' => 4,
            'month'  => 1,
            'day'    => 1,
            'year'   => 2013
        );
        $datetimeparts3 = array( // 12:13:14am May 1, 2013 (for DST test)
            'hour'   => 12,
            'minute' => 13,
            'second' => 14,
            'month'  => 5,
            'day'    => 1,
            'year'   => 2013
        );
        return array(
                array($datetimeparts1, 0, $today101gmt),
                array($datetimeparts2, 14, mktime(2, 3, 4, 1, 1, 2013)),
                array($datetimeparts2, 13.1, mktime(2, 3, 4, 1, 1, 2013)),
                array($datetimeparts2, 12, 1356962584),
                array($datetimeparts2, -12, 1357048984),
                array($datetimeparts2, 12.5, 1356960784),
                array($datetimeparts2, 'America/Toronto', 1357023784), // w/o DST
                array($datetimeparts3, 'America/Toronto', 1367424794), // w/ DST
        );
    }

    /**
     * Test pm_gmt_from_usertime() function
     * @param array $intimeparts the input time components array
     * @param float|int|string $timezone the timezone of $intimestamp
     * @param int $outtimestamp the GMT timestamp for $intimestamp in $timezone
     * @dataProvider pm_timestamp_dataprovider
     */
    public function test_pm_timestamp($intimeparts, $timezone, $outtimestamp) {
        if (get_user_timezone_offset($timezone) == 99) {
            $this->markTestSkipped("\nSkipping test_pm_gmt_from_usertime() with undefined timezone = '{$timezone}'\n");
        } else {
            $this->assertEquals($outtimestamp, pm_timestamp($intimeparts['hour'], $intimeparts['minute'], $intimeparts['second'], $intimeparts['month'], $intimeparts['day'], $intimeparts['year'], $timezone));
        }
    }
}
