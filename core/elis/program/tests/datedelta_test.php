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

// ELIS libs.
require_once(elispm::lib('datedelta.class.php'));

/**
 * Test datedelta.
 * @group elis_program
 */
class datedelta_testcase extends elis_database_test {

    /**
     * Test datedelta data provider values format: array( input string, isvalid bool, iszero bool )
     * @return array The test data
     */
    public function dataprovider_datedelta() {
        return array(
                array('', true, true),
                array('0h', true, true),
                array('0h, 0d, 0m, 0y', true, true),
                array('1h', true, false),
                array('1g', false, null),
                array('10x', false, null),
                array('1h, 1d, 1m, 1y', true, false),
                array('2h, 20d, 10m, 4y', true, false),
        );
    }

    /**
     * Test datedelta methods: validate() & is_zero()
     * @dataProvider dataprovider_datedelta
     * @param string $instr The input string.
     * @param bool $isvalid Whether the input string is valid.
     * @param bool $iszero Whether the input string is zero.
     */
    public function test_datedelta($instr, $isvalid, $iszero) {
        $this->assertEquals($isvalid, datedelta::validate($instr));
        if ($isvalid === true) {
            $dd = new datedelta($instr);
            $this->assertEquals($iszero, $dd->is_zero());
        }
    }
}