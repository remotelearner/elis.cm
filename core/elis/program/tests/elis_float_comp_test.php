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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or latter
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @authour    Brent Boghosian <brent.boghosian@remote-learner.net>
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once(elispm::lib('lib.php'));

/**
 * Test the elis_float_comp function.
 * @group elis_program
 */
class elis_float_comp_test extends basic_testcase {

    /**
     * test_elis_float_comp() data provider
     * values format: array( num1, num2, operator, expectedresult)
     * @return array the test data
     */
    public function elis_float_comp_dataprovider() {
        return array(
                array('1.00001', '1.00002', '>=', false),
                array('1.00001', '1.00002', '=', false),
                array('1.00001', '1.00002', '==', false),
                array('1.00001', '1.00002', '<', true),
                array('1.00001', '1.00002', '<=', true),
                array('1.00001', '1.00002', '!=', true),
                array('1.00002', '1.00002', '>=', true),
                array('1.00002', '1.00002', '>', false),
                array('1.00002', '1.00002', '=', true),
                array('1.00002', '1.00002', '==', true),
                array('1.00002', '1.00002', '!=', false),
                array('1.00002', '1.00002', '<', false),
                array('1.00002', '1.00002', '<=', true),
                array('1.00002', '1.000025', '<=', true),
                array('1.00002', '1.000025', '<', true),
                array('1.00002', '1.000025', '>=', false),
                array('1.00002', '1.000025', '>', false),
                array('1.00002', '1.000025', '=', false),
                array('1.00002', '1.000025', '==', false),
                array('1.00002', '1.000025', '!=', true),
                array('1.00001', '1', '<=', false),
                array('1.00001', '1', '<', false),
                array('1.00001', '1', '>=', true),
                array('1.00001', '1', '>', true),
                array('1.00001', '1', '=', false),
                array('1.00001', '1', '==', false),
                array('1.00001', '1', '!=', true),
        );
    }

    /**
     * Test elis_float_comp() function
     * @dataProvider elis_float_comp_dataprovider
     * @param float $num1 The first number to compare.
     * @param float $num2 The second number to compare.
     * @param string $op The comparison operation.
     * @param bool $expectedresult The expected result.
     */
    public function test_elis_float_comp($num1, $num2, $op, $expectedresult) {
        $this->assertEquals($expectedresult, elis_float_comp($num1, $num2, $op));
        $this->assertEquals($expectedresult, elis_float_comp($num1, $num2, $op, true));
    }
}
