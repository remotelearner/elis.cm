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

/**
 * Quick test of all /elis/program/lib/data/*.class.php files to check $_dbfield_ properties and associations.
 * @group elis_program
 */
class dataobjectbuildintest_testcase extends basic_testcase {

    /**
     * Test that ALL data classes have correct DB fields & associations
     */
    public function test_alldatafiles() {
        global $CFG;
        $datadir = "{$CFG->dirroot}/elis/program/lib/data";
        $datafiles = scandir($datadir);
        $dbfielderrors = 0;
        $associationerrors = 0;
        foreach ($datafiles as $datafile) {
            $filename = "{$datadir}/{$datafile}";
            if (!is_dir($filename) &&
                ($ext = strpos($datafile, '.class.php')) !== false &&
                strlen($datafile) == ($ext + 10)) {
                require_once($filename);
                $classname = substr($datafile, 0, $ext);
                $testobj = new $classname();
                if (!is_a($testobj, 'elis_data_object')) {
                    continue;
                }
                if (!$testobj->_test_dbfields()) {
                    $dbfielderrors++;
                }
                if (!$testobj->_test_associations()) {
                    $associationerrors++;
                }
            }
        }
        $errorstr = '';
        if ($dbfielderrors) {
            $errorstr .= "{$dbfield_errors} files(s) with \$_dbfield_ property errors. ";
        }
        if ($associationerrors) {
            $errorstr .= "{$association_errors} files(s) with association errors. ";
        }
        if (!empty($errorstr)) {
            $errorstr .= 'Check error_log for details!';
        }

        $this->assertEquals(0, $dbfielderrors + $associationerrors, $errorstr);
    }
}